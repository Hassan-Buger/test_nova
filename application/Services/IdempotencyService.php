<?php

namespace Application\Services;

use Application\Core\Database;
use PDO;
use Throwable;

class IdempotencyService
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance();
    }

    public function get(string $key): ?array
    {
        $cleanKey = trim($key);
        if ($cleanKey === '') {
            return null;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT response_status, response_body
                FROM api_idempotency_keys
                WHERE idempotency_key = :key AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute(['key' => $cleanKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $body = json_decode($row['response_body'], true);
                return [
                    'status' => (int)$row['response_status'],
                    'body' => is_array($body) ? $body : []
                ];
            }
        } catch (Throwable $e) {
            // Non-blocking fallback if table or DB is temporarily unavailable
        }

        return null;
    }

    public function save(
        string $key,
        string $method,
        string $path,
        string $payloadHash,
        int $statusCode,
        array $responseBody,
        int $ttlSeconds = 86400
    ): void {
        $cleanKey = trim($key);
        if ($cleanKey === '') {
            return;
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO api_idempotency_keys 
                    (idempotency_key, request_method, request_path, request_hash, response_status, response_body, created_at, expires_at)
                VALUES 
                    (:key, :method, :path, :hash, :status, :body, NOW(), DATE_ADD(NOW(), INTERVAL :ttl SECOND))
                ON DUPLICATE KEY UPDATE
                    response_status = VALUES(response_status),
                    response_body = VALUES(response_body),
                    expires_at = DATE_ADD(NOW(), INTERVAL :ttl SECOND)
            ");
            $stmt->execute([
                'key' => $cleanKey,
                'method' => strtoupper($method),
                'path' => $path,
                'hash' => $payloadHash,
                'status' => $statusCode,
                'body' => json_encode($responseBody, JSON_UNESCAPED_UNICODE),
                'ttl' => $ttlSeconds
            ]);
        } catch (Throwable $e) {
            // Non-blocking fallback
        }
    }
}
