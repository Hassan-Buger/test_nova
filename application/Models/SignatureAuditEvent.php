<?php

namespace Application\Models;

use Application\Core\Model;
use Application\Core\Request;
use PDO;

class SignatureAuditEvent extends Model
{
    public function log(
        int $requestId,
        string $eventType,
        string $description,
        ?int $signerId = null,
        ?int $userId = null,
        array $metadata = []
    ): int {
        $request = new Request();
        $ip = $request->getIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = $this->db->prepare("
            INSERT INTO signature_audit_events (
                request_id, signer_id, user_id, event_type,
                event_description, metadata, ip_address, user_agent
            ) VALUES (
                :request_id, :signer_id, :user_id, :event_type,
                :event_description, :metadata, :ip_address, :user_agent
            )
        ");

        $stmt->execute([
            'request_id'        => $requestId,
            'signer_id'         => $signerId,
            'user_id'           => $userId,
            'event_type'        => $eventType,
            'event_description' => $description,
            'metadata'          => !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
            'ip_address'        => $ip ? substr($ip, 0, 45) : null,
            'user_agent'        => $userAgent ? substr($userAgent, 0, 255) : null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function getByRequestId(int $requestId): array
    {
        $stmt = $this->db->prepare("
            SELECT sae.*,
                   u.name AS user_name, u.email AS user_email,
                   ss.name AS signer_name, ss.email AS signer_email
            FROM signature_audit_events sae
            LEFT JOIN users u ON u.id = sae.user_id
            LEFT JOIN signature_signers ss ON ss.id = sae.signer_id
            WHERE sae.request_id = :request_id
            ORDER BY sae.created_at ASC, sae.id ASC
        ");
        $stmt->execute(['request_id' => $requestId]);
        return array_map(function ($row) {
            $row['metadata'] = !empty($row['metadata']) ? json_decode((string)$row['metadata'], true) : [];
            return $row;
        }, $stmt->fetchAll());
    }
}
