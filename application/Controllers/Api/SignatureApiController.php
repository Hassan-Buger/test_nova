<?php

namespace Application\Controllers\Api;

use Application\Config\App;
use Application\Core\Database;
use Application\Core\Request;
use Application\Core\Response;
use Application\Models\AuditLog;
use Application\Models\Document;
use Application\Models\SignatureAuditEvent;
use Application\Models\SignatureRequest;
use Application\Models\SignatureSigner;
use Application\Services\IdempotencyService;
use Application\Services\SignatureService;
use PDO;
use Throwable;

class SignatureApiController
{
    private PDO $db;
    private AuditLog $auditLog;
    private IdempotencyService $idempotency;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->auditLog = new AuditLog();
        $this->idempotency = new IdempotencyService($this->db);
    }

    public function listByCompany(Request $request, Response $response, string $id): void
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

        $page = max(1, (int)$request->input('page', 1));
        $perPage = max(1, min((int)$request->input('per_page', 20), 100));
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM signature_requests
            WHERE entity_id = :entity_id AND deleted_at IS NULL
        ");
        $countStmt->execute(['entity_id' => $cleanId]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT sr.*, d.filename AS document_filename
            FROM signature_requests sr
            LEFT JOIN documents d ON d.id = sr.document_id
            WHERE sr.entity_id = :entity_id AND sr.deleted_at IS NULL
            ORDER BY sr.created_at DESC, sr.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute(['entity_id' => $cleanId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        $sigSignerModel = new SignatureSigner();
        $baseUrl = rtrim(App::get('url'), '/');

        foreach ($rows as $row) {
            $reqId = (int)$row['id'];
            $signers = $sigSignerModel->getByRequestId($reqId);

            $signersSummary = [];
            foreach ($signers as $s) {
                $signersSummary[] = [
                    'id' => (int)$s['id'],
                    'name' => $s['name'],
                    'email' => $s['email'],
                    'role' => $s['role'] ?? 'signer',
                    'status' => $s['status'] ?? 'pending',
                    'signed_at' => !empty($s['signed_at']) ? gmdate('Y-m-d\TH:i:s\Z', strtotime($s['signed_at'])) : null,
                    'signing_url' => $baseUrl . '/sign/' . $s['token'],
                ];
            }

            $createdAt = $row['created_at'] ? gmdate('Y-m-d\TH:i:s\Z', strtotime($row['created_at'])) : null;
            $completedAt = !empty($row['completed_at']) ? gmdate('Y-m-d\TH:i:s\Z', strtotime($row['completed_at'])) : null;

            $data[] = [
                'id' => $reqId,
                'company_id' => 'comp_' . $cleanId,
                'title' => $row['title'],
                'document_id' => (int)$row['document_id'],
                'document_filename' => $row['document_filename'] ?? '',
                'status' => $row['status'],
                'signing_order' => $row['signing_order'] ?? 'parallel',
                'signers' => $signersSummary,
                'created_at' => $createdAt,
                'completed_at' => $completedAt,
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

    public function create(Request $request, Response $response, string $id): void
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

        $payload = $request->getJsonBody();
        $title = trim((string)($payload['title'] ?? ''));
        $docId = (int)($payload['document_id'] ?? 0);
        $signers = $payload['signers'] ?? [];

        if ($title === '') {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'The title field is required.'
                ]
            ], 422);
            return;
        }

        if (empty($signers) || !is_array($signers)) {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'At least one signer is required in the signers array.'
                ]
            ], 422);
            return;
        }

        // Check Idempotency Key
        $idempotencyKey = $request->getHeader('Idempotency-Key');
        if (!empty($idempotencyKey)) {
            $cached = $this->idempotency->get($idempotencyKey);
            if ($cached !== null) {
                $response->json($cached['body'], $cached['status']);
                return;
            }
        }

        // Verify company exists
        $stmt = $this->db->prepare("
            SELECT id, client_id, company_name
            FROM client_entities
            WHERE id = :id AND deleted_at IS NULL
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

        $clientId = (int)$entity['client_id'];

        // If no document_id provided, locate or create an initial document record
        if ($docId <= 0) {
            $docStmt = $this->db->prepare("
                SELECT id FROM documents
                WHERE entity_id = :entity_id AND deleted_at IS NULL
                ORDER BY id DESC LIMIT 1
            ");
            $docStmt->execute(['entity_id' => $cleanId]);
            $existingDoc = $docStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingDoc) {
                $docId = (int)$existingDoc['id'];
            } else {
                // Auto-create document for this signing request
                $docIns = $this->db->prepare("
                    INSERT INTO documents (client_id, entity_id, scope, uploaded_by_user_id, direction, filename, stored_path, description, status, created_at)
                    VALUES (:client_id, :entity_id, 'company', 1, 'from_trinova', :filename, :stored_path, :desc, 'Ready', NOW())
                ");
                $safeFilename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $title) . '.pdf';
                $storedPath = bin2hex(random_bytes(16)) . '.pdf';
                $docIns->execute([
                    'client_id' => $clientId,
                    'entity_id' => $cleanId,
                    'filename' => $safeFilename,
                    'stored_path' => $storedPath,
                    'desc' => 'Document for: ' . $title,
                ]);
                $docId = (int)$this->db->lastInsertId();
            }
        }

        // Default fields if none provided
        $fieldsData = $payload['fields'] ?? [];
        if (empty($fieldsData)) {
            $fieldsData = [
                [
                    'signer_index' => 0,
                    'type' => 'signature',
                    'page' => 1,
                    'position_x' => 10.0,
                    'position_y' => 80.0,
                    'width' => 24.0,
                    'height' => 8.0,
                    'required' => 1,
                ]
            ];
        }

        $days = max(1, (int)($payload['expires_in_days'] ?? 30));
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$days} days"));

        $requestData = [
            'document_id' => $docId,
            'title' => $title,
            'send_now' => 1,
            'signing_order' => $payload['signing_order'] ?? 'parallel',
            'allow_drawn_signature' => $payload['allow_drawn_signature'] ?? 1,
            'allow_typed_signature' => $payload['allow_typed_signature'] ?? 1,
            'allow_upload_signature' => $payload['allow_upload_signature'] ?? 1,
            'expires_at' => $expiresAt,
        ];

        try {
            // Use existing TriNova SignatureService engine
            $requestId = SignatureService::createRequest($requestData, $signers, $fieldsData, 1);
        } catch (Throwable $e) {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'SIGNATURE_CREATION_FAILED',
                    'message' => 'Failed to create digital signature request: ' . $e->getMessage()
                ]
            ], 500);
            return;
        }

        // Retrieve created signers
        $sigSignerModel = new SignatureSigner();
        $createdSigners = $sigSignerModel->getByRequestId($requestId);

        $baseUrl = rtrim(App::get('url'), '/');
        $signersResponse = [];
        foreach ($createdSigners as $cs) {
            $signersResponse[] = [
                'id' => (int)$cs['id'],
                'name' => $cs['name'],
                'email' => $cs['email'],
                'role' => $cs['role'],
                'status' => $cs['status'],
                'signing_url' => $baseUrl . '/sign/' . $cs['token'],
            ];
        }

        $now = gmdate('Y-m-d\TH:i:s\Z');
        $responseData = [
            'status' => 'success',
            'data' => [
                'id' => $requestId,
                'company_id' => 'comp_' . $cleanId,
                'document_id' => $docId,
                'title' => $title,
                'status' => 'pending',
                'signers' => $signersResponse,
                'created_at' => $now,
            ]
        ];

        // Audit log
        $this->auditLog->log(
            null,
            'create_signing_request',
            'signature_requests',
            $requestId,
            [
                'company_id' => $cleanId,
                'title' => $title,
                'signers_count' => count($signers),
                'author' => 'sloane_ceo_command_centre'
            ],
            $request->getIp()
        );

        if (!empty($idempotencyKey)) {
            $payloadHash = hash('sha256', json_encode($payload));
            $this->idempotency->save(
                $idempotencyKey,
                'POST',
                $request->getUri(),
                $payloadHash,
                201,
                $responseData,
                86400
            );
        }

        $response->json($responseData, 201);
    }

    public function audit(Request $request, Response $response, string $id): void
    {
        $cleanId = (int)$id;
        if ($cleanId <= 0) {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'INVALID_SIGNATURE_ID',
                    'message' => 'The provided signature request ID is invalid.'
                ]
            ], 400);
            return;
        }

        $stmt = $this->db->prepare("
            SELECT sr.*, d.filename AS document_filename
            FROM signature_requests sr
            LEFT JOIN documents d ON d.id = sr.document_id
            WHERE sr.id = :id AND sr.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['id' => $cleanId]);
        $sigReq = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sigReq) {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'SIGNATURE_REQUEST_NOT_FOUND',
                    'message' => "Signature request with ID {$id} was not found."
                ]
            ], 404);
            return;
        }

        $auditModel = new SignatureAuditEvent();
        $rawEvents = $auditModel->getByRequestId($cleanId);

        $events = [];
        foreach ($rawEvents as $ev) {
            $events[] = [
                'id' => (int)$ev['id'],
                'event_type' => $ev['event_type'],
                'description' => $ev['event_description'],
                'signer_name' => $ev['signer_name'] ?? null,
                'signer_email' => $ev['signer_email'] ?? null,
                'ip_address' => $ev['ip_address'] ?? null,
                'user_agent' => $ev['user_agent'] ?? null,
                'metadata' => $ev['metadata'] ?? [],
                'created_at' => $ev['created_at'] ? gmdate('Y-m-d\TH:i:s\Z', strtotime($ev['created_at'])) : null,
            ];
        }

        $response->json([
            'status' => 'success',
            'data' => [
                'request_id' => $cleanId,
                'title' => $sigReq['title'],
                'status' => $sigReq['status'],
                'original_checksum_sha256' => $sigReq['original_checksum_sha256'] ?? null,
                'signed_checksum_sha256' => $sigReq['signed_checksum_sha256'] ?? null,
                'created_at' => $sigReq['created_at'] ? gmdate('Y-m-d\TH:i:s\Z', strtotime($sigReq['created_at'])) : null,
                'completed_at' => !empty($sigReq['completed_at']) ? gmdate('Y-m-d\TH:i:s\Z', strtotime($sigReq['completed_at'])) : null,
                'events' => $events
            ]
        ], 200);
    }
}
