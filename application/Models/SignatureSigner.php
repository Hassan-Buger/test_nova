<?php

namespace Application\Models;

use Application\Core\Model;
use PDO;

class SignatureSigner extends Model
{
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM signature_signers WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare("
            SELECT ss.*, 
                   sr.title AS request_title, sr.status AS request_status, sr.signing_order,
                   sr.document_id, sr.client_id, sr.entity_id, sr.expires_at AS request_expires_at,
                   sr.allow_drawn_signature, sr.allow_typed_signature, sr.allow_upload_signature,
                   d.filename AS document_filename, d.stored_path AS document_stored_path
            FROM signature_signers ss
            JOIN signature_requests sr ON sr.id = ss.request_id
            JOIN documents d ON d.id = sr.document_id
            WHERE ss.token = :token AND sr.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['token' => $token]);
        return $stmt->fetch() ?: null;
    }

    public function getByRequestId(int $requestId): array
    {
        $stmt = $this->db->prepare("
            SELECT ss.*,
                   (SELECT COUNT(*) FROM signature_fields sf WHERE sf.signer_id = ss.id) AS total_fields,
                   (SELECT COUNT(*) FROM signature_fields sf WHERE sf.signer_id = ss.id AND sf.inserted = 1) AS signed_fields
            FROM signature_signers ss
            WHERE ss.request_id = :request_id
            ORDER BY ss.signing_order ASC, ss.id ASC
        ");
        $stmt->execute(['request_id' => $requestId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $token = !empty($data['token']) ? $data['token'] : bin2hex(random_bytes(32));
        $stmt = $this->db->prepare("
            INSERT INTO signature_signers (
                request_id, user_id, name, email, role,
                signing_order, token, status, read_status, sent_at
            ) VALUES (
                :request_id, :user_id, :name, :email, :role,
                :signing_order, :token, :status, :read_status, :sent_at
            )
        ");
        $stmt->execute([
            'request_id'    => (int)$data['request_id'],
            'user_id'       => !empty($data['user_id']) ? (int)$data['user_id'] : null,
            'name'          => trim((string)$data['name']),
            'email'         => strtolower(trim((string)$data['email'])),
            'role'          => $data['role'] ?? 'signer',
            'signing_order' => isset($data['signing_order']) ? (int)$data['signing_order'] : 1,
            'token'         => $token,
            'status'        => $data['status'] ?? 'pending',
            'read_status'   => 'not_opened',
            'sent_at'       => $data['sent_at'] ?? date('Y-m-d H:i:s'),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function markOpened(int $id, ?string $ip = null, ?string $userAgent = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE signature_signers 
            SET read_status = 'opened', 
                opened_at = COALESCE(opened_at, NOW()),
                ip_address = COALESCE(:ip, ip_address),
                user_agent = COALESCE(:ua, user_agent)
            WHERE id = :id
        ");
        return $stmt->execute([
            'ip' => $ip ? substr($ip, 0, 45) : null,
            'ua' => $userAgent ? substr($userAgent, 0, 255) : null,
            'id' => $id,
        ]);
    }

    public function markSigned(int $id, ?string $ip = null, ?string $userAgent = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE signature_signers 
            SET status = 'signed', 
                signed_at = NOW(),
                ip_address = COALESCE(:ip, ip_address),
                user_agent = COALESCE(:ua, user_agent)
            WHERE id = :id AND status != 'signed'
        ");
        return $stmt->execute([
            'ip' => $ip ? substr($ip, 0, 45) : null,
            'ua' => $userAgent ? substr($userAgent, 0, 255) : null,
            'id' => $id,
        ]);
    }

    public function markDeclined(int $id, string $reason, ?string $ip = null, ?string $userAgent = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE signature_signers 
            SET status = 'declined', 
                declined_at = NOW(),
                decline_reason = :reason,
                ip_address = COALESCE(:ip, ip_address),
                user_agent = COALESCE(:ua, user_agent)
            WHERE id = :id
        ");
        return $stmt->execute([
            'reason' => $reason,
            'ip'     => $ip ? substr($ip, 0, 45) : null,
            'ua'     => $userAgent ? substr($userAgent, 0, 255) : null,
            'id'     => $id,
        ]);
    }

    public function isTurnToSign(int $signerId): bool
    {
        $signer = $this->find($signerId);
        if (!$signer) {
            return false;
        }

        $requestStmt = $this->db->prepare("SELECT signing_order FROM signature_requests WHERE id = :id");
        $requestStmt->execute(['id' => $signer['request_id']]);
        $signingOrder = $requestStmt->fetchColumn();

        if ($signingOrder !== 'sequential') {
            return true;
        }

        // Check if all signers with a strictly lower signing order have signed
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM signature_signers 
            WHERE request_id = :req_id 
              AND signing_order < :order 
              AND role IN ('signer', 'approver')
              AND status != 'signed'
        ");
        $stmt->execute([
            'req_id' => $signer['request_id'],
            'order'  => (int)$signer['signing_order'],
        ]);
        return (int)$stmt->fetchColumn() === 0;
    }

    public function haveAllSigned(int $requestId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM signature_signers 
            WHERE request_id = :req_id 
              AND role IN ('signer', 'approver')
              AND status != 'signed'
        ");
        $stmt->execute(['req_id' => $requestId]);
        return (int)$stmt->fetchColumn() === 0;
    }
}
