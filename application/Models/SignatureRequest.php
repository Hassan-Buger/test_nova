<?php

namespace Application\Models;

use Application\Core\Model;
use PDO;

class SignatureRequest extends Model
{
    public static function generateQrToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT sr.*, 
                   d.filename AS original_filename, d.stored_path AS original_stored_path,
                   sd.filename AS signed_filename, sd.stored_path AS signed_stored_path,
                   cu.name AS client_name,
                   ce.company_name AS entity_name, ce.entity_scope,
                   u.name AS creator_name, u.email AS creator_email
            FROM signature_requests sr
            JOIN documents d ON d.id = sr.document_id
            LEFT JOIN documents sd ON sd.id = sr.signed_document_id
            LEFT JOIN clients c ON c.id = sr.client_id
            LEFT JOIN users cu ON cu.id = c.user_id
            LEFT JOIN client_entities ce ON ce.id = sr.entity_id
            LEFT JOIN users u ON u.id = sr.created_by_user_id
            WHERE sr.id = :id AND sr.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByQrToken(string $qrToken): ?array
    {
        $stmt = $this->db->prepare("
            SELECT sr.*, 
                   d.filename AS original_filename,
                   sd.filename AS signed_filename, sd.stored_path AS signed_stored_path,
                   cu.name AS client_name,
                   ce.company_name AS entity_name,
                   u.name AS creator_name
            FROM signature_requests sr
            JOIN documents d ON d.id = sr.document_id
            LEFT JOIN documents sd ON sd.id = sr.signed_document_id
            LEFT JOIN clients c ON c.id = sr.client_id
            LEFT JOIN users cu ON cu.id = c.user_id
            LEFT JOIN client_entities ce ON ce.id = sr.entity_id
            LEFT JOIN users u ON u.id = sr.created_by_user_id
            WHERE sr.qr_token = :qr_token AND sr.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['qr_token' => $qrToken]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $qrToken = $data['qr_token'] ?? bin2hex(random_bytes(16));
        $stmt = $this->db->prepare("
            INSERT INTO signature_requests (
                document_id, client_id, entity_id, created_by_user_id,
                title, status, signing_order, qr_token,
                allow_drawn_signature, allow_typed_signature, allow_upload_signature,
                expires_at
            ) VALUES (
                :document_id, :client_id, :entity_id, :created_by_user_id,
                :title, :status, :signing_order, :qr_token,
                :allow_drawn, :allow_typed, :allow_upload,
                :expires_at
            )
        ");
        $stmt->execute([
            'document_id'         => (int)$data['document_id'],
            'client_id'           => (int)$data['client_id'],
            'entity_id'           => !empty($data['entity_id']) ? (int)$data['entity_id'] : null,
            'created_by_user_id'  => (int)$data['created_by_user_id'],
            'title'               => trim((string)$data['title']),
            'status'              => $data['status'] ?? 'draft',
            'signing_order'       => $data['signing_order'] ?? 'parallel',
            'qr_token'            => $qrToken,
            'allow_drawn'         => isset($data['allow_drawn_signature']) ? (int)(bool)$data['allow_drawn_signature'] : 1,
            'allow_typed'         => isset($data['allow_typed_signature']) ? (int)(bool)$data['allow_typed_signature'] : 1,
            'allow_upload'        => isset($data['allow_upload_signature']) ? (int)(bool)$data['allow_upload_signature'] : 1,
            'expires_at'          => !empty($data['expires_at']) ? $data['expires_at'] : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("UPDATE signature_requests SET status = :status WHERE id = :id");
        return $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function markCompleted(int $id, int $signedDocId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE signature_requests 
            SET status = 'completed', signed_document_id = :signed_doc_id, completed_at = NOW() 
            WHERE id = :id
        ");
        return $stmt->execute(['signed_doc_id' => $signedDocId, 'id' => $id]);
    }

    public function markDeclined(int $id, string $reason = ''): bool
    {
        $stmt = $this->db->prepare("
            UPDATE signature_requests 
            SET status = 'declined', decline_reason = :reason, declined_at = NOW() 
            WHERE id = :id
        ");
        return $stmt->execute(['reason' => $reason, 'id' => $id]);
    }

    public function paginateWithDetails(array $filters, int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = max(5, min($perPage, 50));
        $where = ['sr.deleted_at IS NULL'];
        $params = [];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(sr.title LIKE :search_title OR d.filename LIKE :search_file OR cu.name LIKE :search_client OR ce.company_name LIKE :search_entity)';
            $like = '%' . $search . '%';
            $params['search_title'] = $like;
            $params['search_file'] = $like;
            $params['search_client'] = $like;
            $params['search_entity'] = $like;
        }

        if (!empty($filters['status'])) {
            $where[] = 'sr.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['client_id'])) {
            $where[] = 'sr.client_id = :client_id';
            $params['client_id'] = (int)$filters['client_id'];
        }

        if (!empty($filters['entity_id'])) {
            $where[] = 'sr.entity_id = :entity_id';
            $params['entity_id'] = (int)$filters['entity_id'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $joins = "
            JOIN documents d ON d.id = sr.document_id
            LEFT JOIN documents sd ON sd.id = sr.signed_document_id
            LEFT JOIN clients c ON c.id = sr.client_id
            LEFT JOIN users cu ON cu.id = c.user_id
            LEFT JOIN client_entities ce ON ce.id = sr.entity_id
            LEFT JOIN users u ON u.id = sr.created_by_user_id
        ";

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM signature_requests sr {$joins} {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare("
            SELECT sr.*, 
                   d.filename AS original_filename,
                   sd.filename AS signed_filename, sd.stored_path AS signed_stored_path,
                   cu.name AS client_name,
                   ce.company_name AS entity_name,
                   u.name AS creator_name,
                   (SELECT COUNT(*) FROM signature_signers ss WHERE ss.request_id = sr.id) AS total_signers,
                   (SELECT COUNT(*) FROM signature_signers ss WHERE ss.request_id = sr.id AND ss.status = 'signed') AS signed_signers
            FROM signature_requests sr
            {$joins}
            {$whereSql}
            ORDER BY sr.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return [
            'items'       => $stmt->fetchAll(),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    public function getPendingForClientUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT sr.*, ss.id AS signer_id, ss.token AS signer_token, ss.status AS signer_status,
                   d.filename AS original_filename,
                   ce.company_name AS entity_name
            FROM signature_signers ss
            JOIN signature_requests sr ON sr.id = ss.request_id
            JOIN documents d ON d.id = sr.document_id
            LEFT JOIN client_entities ce ON ce.id = sr.entity_id
            JOIN users u ON (u.id = ss.user_id OR LOWER(u.email) = LOWER(ss.email))
            WHERE u.id = :user_id 
              AND ss.status = 'pending'
              AND sr.status = 'pending'
              AND sr.deleted_at IS NULL
            ORDER BY sr.created_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE signature_requests SET deleted_at = NOW(), status = 'cancelled' WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
