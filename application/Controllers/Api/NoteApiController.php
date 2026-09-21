<?php

namespace Application\Controllers\Api;

use Application\Core\Database;
use Application\Core\Request;
use Application\Core\Response;
use Application\Models\AuditLog;
use Application\Services\IdempotencyService;
use PDO;
use Throwable;

class NoteApiController
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

        // Strict CEO Brain Privacy Boundary Check
        $payload = $request->getJsonBody();
        $confirm = $payload['confirm_write_to_trinova'] ?? false;
        if ($confirm !== true) {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'PRIVACY_BOUNDARY_CONFIRMATION_REQUIRED',
                    'message' => "Explicit CEO confirmation ('confirm_write_to_trinova': true) is required to write notes to TriNova."
                ]
            ], 403);
            return;
        }

        $text = trim((string)($payload['text'] ?? ''));
        if ($text === '') {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Note text cannot be empty.'
                ]
            ], 422);
            return;
        }

        $visibility = trim((string)($payload['visibility'] ?? 'staff_only'));
        if (!in_array($visibility, ['staff_only', 'client_visible'], true)) {
            $visibility = 'staff_only';
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
            SELECT id, client_id, company_name, attributes
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
        $noteId = 'note_' . bin2hex(random_bytes(8));
        $now = gmdate('Y-m-d\TH:i:s\Z');

        $noteRecord = [
            'id' => $noteId,
            'company_id' => 'comp_' . $cleanId,
            'client_id' => $clientId,
            'text' => $text,
            'visibility' => $visibility,
            'author' => 'sloane_ceo_command_centre',
            'created_at' => $now,
        ];

        // 1. Append to client_entities.attributes['notes']
        $attributes = [];
        if (!empty($entity['attributes'])) {
            $decoded = json_decode($entity['attributes'], true);
            if (is_array($decoded)) {
                $attributes = $decoded;
            }
        }
        if (!isset($attributes['notes']) || !is_array($attributes['notes'])) {
            $attributes['notes'] = [];
        }
        $attributes['notes'][] = $noteRecord;

        $updateStmt = $this->db->prepare("
            UPDATE client_entities
            SET attributes = :attributes
            WHERE id = :id
        ");
        $updateStmt->execute([
            'attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE),
            'id' => $cleanId
        ]);

        // 2. Also append note entry to clients.notes if client exists
        try {
            $clientStmt = $this->db->prepare("SELECT notes FROM clients WHERE id = :id LIMIT 1");
            $clientStmt->execute(['id' => $clientId]);
            $clientNotes = (string)$clientStmt->fetchColumn();
            $stamp = date('Y-m-d H:i') . ' [Sloane CEO Note - ' . $entity['company_name'] . ']: ' . $text;
            $newNotes = trim($clientNotes . "\n" . $stamp);

            $updateClientStmt = $this->db->prepare("UPDATE clients SET notes = :notes WHERE id = :id");
            $updateClientStmt->execute(['notes' => $newNotes, 'id' => $clientId]);
        } catch (Throwable $e) {
            // Non-critical if clients table append fails
        }

        // 3. Log to audit_log
        $this->auditLog->log(
            null,
            'create_internal_note',
            'client_entities',
            $cleanId,
            [
                'note_id' => $noteId,
                'visibility' => $visibility,
                'idempotency_key' => $idempotencyKey,
                'author' => 'sloane_ceo_command_centre',
                'text_preview' => mb_substr($text, 0, 100)
            ],
            $request->getIp()
        );

        $responseData = [
            'status' => 'success',
            'data' => [
                'id' => $noteId,
                'company_id' => 'comp_' . $cleanId,
                'text' => $text,
                'visibility' => $visibility,
                'created_at' => $now,
            ]
        ];

        // Save Idempotency cache if key provided
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
}
