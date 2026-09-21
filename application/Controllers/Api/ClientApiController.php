<?php

namespace Application\Controllers\Api;

use Application\Core\Database;
use Application\Core\Request;
use Application\Core\Response;
use PDO;
use Throwable;

class ClientApiController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function index(Request $request, Response $response): void
    {
        $q = trim((string)$request->input('q', ''));
        $type = trim((string)$request->input('type', ''));
        $page = max(1, (int)$request->input('page', 1));
        $perPage = max(1, min((int)$request->input('per_page', 20), 100));
        $offset = ($page - 1) * $perPage;

        $where = ["e.deleted_at IS NULL", "c.deleted_at IS NULL"];
        $params = [];

        if ($q !== '') {
            $where[] = "(e.company_name LIKE :q_comp OR e.company_number LIKE :q_num OR u.name LIKE :q_name OR u.email LIKE :q_email)";
            $searchTerm = '%' . $q . '%';
            $params['q_comp'] = $searchTerm;
            $params['q_num'] = $searchTerm;
            $params['q_name'] = $searchTerm;
            $params['q_email'] = $searchTerm;
        }

        if ($type !== '') {
            $where[] = "e.entity_type = :type";
            $params['type'] = $type;
        }

        $whereSql = implode(' AND ', $where);

        // Count total matching
        $countStmt = $this->db->prepare("
            SELECT COUNT(DISTINCT e.id)
            FROM client_entities e
            JOIN clients c ON c.id = e.client_id
            JOIN users u ON u.id = c.user_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Fetch paginated entities
        $stmt = $this->db->prepare("
            SELECT e.id,
                   e.client_id,
                   e.company_name AS name,
                   e.company_number,
                   e.entity_type,
                   e.entity_scope,
                   e.tax_reference,
                   e.attributes,
                   e.created_at,
                   c.phone AS client_phone,
                   c.address AS client_address,
                   u.name AS client_name,
                   u.email AS client_email
            FROM client_entities e
            JOIN clients c ON c.id = e.client_id
            JOIN users u ON u.id = c.user_id
            WHERE {$whereSql}
            ORDER BY e.id ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            $response->json([
                'status' => 'success',
                'data' => [],
                'meta' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage
                ]
            ], 200);
            return;
        }

        $entityIds = array_column($rows, 'id');
        $placeholders = implode(',', array_fill(0, count($entityIds), '?'));

        // Load directors from entity_contacts
        $contactsStmt = $this->db->prepare("
            SELECT entity_id, name, is_primary, email, phone
            FROM entity_contacts
            WHERE entity_id IN ({$placeholders})
            ORDER BY is_primary DESC, id ASC
        ");
        $contactsStmt->execute($entityIds);
        $contactsByEntity = [];
        foreach ($contactsStmt->fetchAll(PDO::FETCH_ASSOC) as $contact) {
            $contactsByEntity[$contact['entity_id']][] = $contact;
        }

        // Load directors from entity_directors
        $directorsStmt = $this->db->prepare("
            SELECT ed.entity_id, u.name, u.email
            FROM entity_directors ed
            JOIN users u ON u.id = ed.user_id
            WHERE ed.entity_id IN ({$placeholders}) AND ed.deleted_at IS NULL
        ");
        $directorsStmt->execute($entityIds);
        $usersByEntity = [];
        foreach ($directorsStmt->fetchAll(PDO::FETCH_ASSOC) as $dir) {
            $usersByEntity[$dir['entity_id']][] = $dir;
        }

        $data = [];
        foreach ($rows as $row) {
            $entityId = (int)$row['id'];
            $directorNames = [];

            if (!empty($contactsByEntity[$entityId])) {
                foreach ($contactsByEntity[$entityId] as $c) {
                    if (!empty($c['name']) && !in_array($c['name'], $directorNames, true)) {
                        $directorNames[] = $c['name'];
                    }
                }
            }

            if (!empty($usersByEntity[$entityId])) {
                foreach ($usersByEntity[$entityId] as $u) {
                    if (!empty($u['name']) && !in_array($u['name'], $directorNames, true)) {
                        $directorNames[] = $u['name'];
                    }
                }
            }

            // Fallback if no directors linked: client user's name
            if (empty($directorNames) && !empty($row['client_name'])) {
                $directorNames[] = $row['client_name'];
            }

            $createdAt = $row['created_at'] ? gmdate('Y-m-d\TH:i:s\Z', strtotime($row['created_at'])) : null;

            $data[] = [
                'id' => $entityId,
                'company_id' => 'comp_' . $entityId,
                'client_id' => (int)$row['client_id'],
                'name' => $row['name'],
                'company_number' => $row['company_number'] !== null && $row['company_number'] !== '' ? $row['company_number'] : null,
                'entity_type' => $row['entity_type'] ?? 'Corporate',
                'directors' => $directorNames,
                'tax_reference' => $row['tax_reference'] !== null && $row['tax_reference'] !== '' ? $row['tax_reference'] : null,
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

    public function show(Request $request, Response $response, string $id): void
    {
        $cleanId = (int)str_replace('comp_', '', $id);
        if ($cleanId <= 0) {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'INVALID_COMPANY_ID',
                    'message' => 'The provided company ID is invalid.'
                ]
            ], 400);
            return;
        }

        $stmt = $this->db->prepare("
            SELECT e.*,
                   c.phone AS client_phone,
                   c.address AS client_address,
                   c.notes AS client_notes,
                   u.name AS client_name,
                   u.email AS client_email
            FROM client_entities e
            JOIN clients c ON c.id = e.client_id
            JOIN users u ON u.id = c.user_id
            WHERE e.id = :id AND e.deleted_at IS NULL AND c.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['id' => $cleanId]);
        $entity = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entity) {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'COMPANY_NOT_FOUND',
                    'message' => "Company with ID {$id} was not found."
                ]
            ], 404);
            return;
        }

        // Fetch contacts
        $contactsStmt = $this->db->prepare("
            SELECT * FROM entity_contacts
            WHERE entity_id = :id
            ORDER BY is_primary DESC, id ASC
        ");
        $contactsStmt->execute(['id' => $cleanId]);
        $contacts = $contactsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch directors
        $directorsStmt = $this->db->prepare("
            SELECT ed.user_id, u.name, u.email
            FROM entity_directors ed
            JOIN users u ON u.id = ed.user_id
            WHERE ed.entity_id = :id AND ed.deleted_at IS NULL
        ");
        $directorsStmt->execute(['id' => $cleanId]);
        $directors = $directorsStmt->fetchAll(PDO::FETCH_ASSOC);

        $directorNames = [];
        $primaryContact = null;

        foreach ($contacts as $c) {
            if (!empty($c['name']) && !in_array($c['name'], $directorNames, true)) {
                $directorNames[] = $c['name'];
            }
            if ($primaryContact === null && ((int)$c['is_primary'] === 1 || !empty($c['email']))) {
                $primaryContact = [
                    'name' => $c['name'],
                    'email' => $c['email'] ?? null,
                    'phone' => $c['phone'] ?? null,
                    'address' => $c['address'] ?? null,
                ];
            }
        }

        foreach ($directors as $d) {
            if (!empty($d['name']) && !in_array($d['name'], $directorNames, true)) {
                $directorNames[] = $d['name'];
            }
        }

        if (empty($directorNames) && !empty($entity['client_name'])) {
            $directorNames[] = $entity['client_name'];
        }

        if ($primaryContact === null) {
            $primaryContact = [
                'name' => $entity['client_name'],
                'email' => $entity['client_email'],
                'phone' => $entity['client_phone'],
                'address' => $entity['client_address'],
            ];
        }

        $attributes = [];
        if (!empty($entity['attributes'])) {
            $decoded = json_decode($entity['attributes'], true);
            if (is_array($decoded)) {
                $attributes = $decoded;
            }
        }

        $createdAt = $entity['created_at'] ? gmdate('Y-m-d\TH:i:s\Z', strtotime($entity['created_at'])) : null;

        $response->json([
            'status' => 'success',
            'data' => [
                'id' => $cleanId,
                'company_id' => 'comp_' . $cleanId,
                'client_id' => (int)$entity['client_id'],
                'name' => $entity['company_name'],
                'company_number' => $entity['company_number'] !== null && $entity['company_number'] !== '' ? $entity['company_number'] : null,
                'entity_type' => $entity['entity_type'] ?? 'Corporate',
                'entity_scope' => $entity['entity_scope'] ?? 'company',
                'tax_reference' => $entity['tax_reference'] !== null && $entity['tax_reference'] !== '' ? $entity['tax_reference'] : null,
                'directors' => $directorNames,
                'primary_contact' => $primaryContact,
                'registered_address' => $primaryContact['address'] ?? $entity['client_address'] ?? null,
                'attributes' => $attributes,
                'created_at' => $createdAt,
            ]
        ], 200);
    }
}
