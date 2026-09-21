<?php

namespace Application\Controllers\Api;

use Application\Core\Database;
use Application\Core\Request;
use Application\Core\Response;
use Application\Models\AuditLog;
use PDO;
use Throwable;

class StatutoryApiController
{
    private PDO $db;
    private AuditLog $auditLog;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auditLog = new AuditLog();
    }

    public function index(Request $request, Response $response): void
    {
        $companyNumber = trim((string)$request->input('company_number', ''));
        $status = trim((string)$request->input('status', ''));
        $entityId = (int)$request->input('entity_id', 0);
        $page = max(1, (int)$request->input('page', 1));
        $perPage = max(1, min((int)$request->input('per_page', 20), 100));
        $offset = ($page - 1) * $perPage;

        $where = [
            "d.deleted_at IS NULL",
            "e.deleted_at IS NULL",
            "(d.type IN ('Accounts', 'Confirmation Statement') OR d.type LIKE '%Accounts%' OR d.type LIKE '%Confirmation Statement%')"
        ];
        $params = [];

        if ($companyNumber !== '') {
            $where[] = "e.company_number = :comp_num";
            $params['comp_num'] = $companyNumber;
        }

        if ($status !== '') {
            $where[] = "d.status = :status";
            $params['status'] = $status;
        }

        if ($entityId > 0) {
            $where[] = "d.entity_id = :entity_id";
            $params['entity_id'] = $entityId;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM deadlines d
            JOIN client_entities e ON e.id = d.entity_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT d.id,
                   d.client_id,
                   d.entity_id,
                   d.type,
                   d.due_date,
                   d.status,
                   d.created_at,
                   e.company_name,
                   e.company_number
            FROM deadlines d
            JOIN client_entities e ON e.id = d.entity_id
            WHERE {$whereSql}
            ORDER BY d.due_date ASC, d.id ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        $today = date('Y-m-d');
        foreach ($rows as $row) {
            $isOverdue = ($row['status'] === 'Overdue') || ($row['due_date'] < $today && $row['status'] !== 'Completed');
            $createdAt = $row['created_at'] ? gmdate('Y-m-d\TH:i:s\Z', strtotime($row['created_at'])) : null;

            $data[] = [
                'id' => (int)$row['id'],
                'entity_id' => (int)$row['entity_id'],
                'company_id' => 'comp_' . $row['entity_id'],
                'client_id' => (int)$row['client_id'],
                'company_name' => $row['company_name'],
                'company_number' => $row['company_number'] !== null && $row['company_number'] !== '' ? $row['company_number'] : null,
                'type' => $row['type'],
                'due_date' => $row['due_date'],
                'status' => $row['status'],
                'is_overdue' => $isOverdue,
                'created_at' => $createdAt,
            ];
        }

        $response->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage
            ]
        ], 200);
    }

    public function sync(Request $request, Response $response): void
    {
        $payload = $request->getJsonBody();
        $records = $payload['records'] ?? [];

        if (empty($records) && !empty($payload['companyNumbers'])) {
            // Empty records array is allowed if no changes needed
            $records = [];
        }

        if (!is_array($records)) {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'INVALID_PAYLOAD',
                    'message' => 'The records field must be an array of statutory sync objects.'
                ]
            ], 400);
            return;
        }

        $syncedCompanies = 0;
        $updatedDeadlines = 0;
        $syncedAt = $payload['synced_at'] ?? gmdate('Y-m-d\TH:i:s\Z');

        $this->db->beginTransaction();
        try {
            foreach ($records as $rec) {
                $compNumber = trim((string)($rec['company_number'] ?? ''));
                if ($compNumber === '') {
                    continue;
                }

                // Find matching client entity
                $stmt = $this->db->prepare("
                    SELECT id, client_id, company_name, attributes
                    FROM client_entities
                    WHERE company_number = :comp_num AND deleted_at IS NULL
                    LIMIT 1
                ");
                $stmt->execute(['comp_num' => $compNumber]);
                $entity = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$entity) {
                    continue;
                }

                $entityId = (int)$entity['id'];
                $clientId = (int)$entity['client_id'];
                $syncedCompanies++;

                // 1. Accounts Deadline sync
                if (!empty($rec['accounts_due_date'])) {
                    $accDue = trim((string)$rec['accounts_due_date']);
                    $accOverdue = !empty($rec['accounts_overdue']);
                    $accStatus = $accOverdue ? 'Overdue' : 'Pending';

                    $checkStmt = $this->db->prepare("
                        SELECT id, status FROM deadlines
                        WHERE entity_id = :entity_id AND type = 'Accounts' AND deleted_at IS NULL
                        LIMIT 1
                    ");
                    $checkStmt->execute(['entity_id' => $entityId]);
                    $existingAcc = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if ($existingAcc) {
                        $newStatus = ($existingAcc['status'] === 'Completed') ? 'Completed' : $accStatus;
                        $upStmt = $this->db->prepare("
                            UPDATE deadlines
                            SET due_date = :due_date, status = :status
                            WHERE id = :id
                        ");
                        $upStmt->execute([
                            'due_date' => $accDue,
                            'status' => $newStatus,
                            'id' => $existingAcc['id']
                        ]);
                        $updatedDeadlines++;
                    } else {
                        $insStmt = $this->db->prepare("
                            INSERT INTO deadlines (client_id, entity_id, scope, type, due_date, status, created_at)
                            VALUES (:client_id, :entity_id, 'company', 'Accounts', :due_date, :status, NOW())
                        ");
                        $insStmt->execute([
                            'client_id' => $clientId,
                            'entity_id' => $entityId,
                            'due_date' => $accDue,
                            'status' => $accStatus
                        ]);
                        $updatedDeadlines++;
                    }
                }

                // 2. Confirmation Statement Deadline sync
                if (!empty($rec['confirmation_statement_due_date'])) {
                    $csDue = trim((string)$rec['confirmation_statement_due_date']);
                    $csOverdue = !empty($rec['cs_overdue']);
                    $csStatus = $csOverdue ? 'Overdue' : 'Pending';

                    $checkStmt = $this->db->prepare("
                        SELECT id, status FROM deadlines
                        WHERE entity_id = :entity_id AND type = 'Confirmation Statement' AND deleted_at IS NULL
                        LIMIT 1
                    ");
                    $checkStmt->execute(['entity_id' => $entityId]);
                    $existingCs = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if ($existingCs) {
                        $newStatus = ($existingCs['status'] === 'Completed') ? 'Completed' : $csStatus;
                        $upStmt = $this->db->prepare("
                            UPDATE deadlines
                            SET due_date = :due_date, status = :status
                            WHERE id = :id
                        ");
                        $upStmt->execute([
                            'due_date' => $csDue,
                            'status' => $newStatus,
                            'id' => $existingCs['id']
                        ]);
                        $updatedDeadlines++;
                    } else {
                        $insStmt = $this->db->prepare("
                            INSERT INTO deadlines (client_id, entity_id, scope, type, due_date, status, created_at)
                            VALUES (:client_id, :entity_id, 'company', 'Confirmation Statement', :due_date, :status, NOW())
                        ");
                        $insStmt->execute([
                            'client_id' => $clientId,
                            'entity_id' => $entityId,
                            'due_date' => $csDue,
                            'status' => $csStatus
                        ]);
                        $updatedDeadlines++;
                    }
                }

                // 3. Update client_entities attributes
                $attributes = [];
                if (!empty($entity['attributes'])) {
                    $decoded = json_decode($entity['attributes'], true);
                    if (is_array($decoded)) {
                        $attributes = $decoded;
                    }
                }
                $attributes['ch_last_checked_at'] = $rec['synced_at'] ?? $syncedAt;
                $attributes['active_proposal_to_strike_off'] = (bool)($rec['active_proposal_to_strike_off'] ?? false);

                $attrStmt = $this->db->prepare("
                    UPDATE client_entities
                    SET attributes = :attributes
                    WHERE id = :id
                ");
                $attrStmt->execute([
                    'attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE),
                    'id' => $entityId
                ]);

                // 4. Log in audit_log
                $this->auditLog->log(
                    null,
                    'companies_house_reconciled',
                    'client_entities',
                    $entityId,
                    [
                        'company_number' => $compNumber,
                        'accounts_due_date' => $rec['accounts_due_date'] ?? null,
                        'confirmation_statement_due_date' => $rec['confirmation_statement_due_date'] ?? null,
                        'active_proposal_to_strike_off' => $attributes['active_proposal_to_strike_off'],
                        'synced_at' => $attributes['ch_last_checked_at']
                    ],
                    $request->getIp()
                );
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'STATUTORY_SYNC_FAILED',
                    'message' => 'Failed to reconcile statutory dates: ' . $e->getMessage()
                ]
            ], 500);
            return;
        }

        $response->json([
            'status' => 'success',
            'data' => [
                'synced_companies' => $syncedCompanies,
                'updated_deadlines' => $updatedDeadlines,
                'synced_at' => $syncedAt
            ]
        ], 200);
    }
}
