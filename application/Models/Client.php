<?php

namespace Application\Models;

use Application\Core\Model;
use PDO;

class Client extends Model
{
    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, u.name, u.email
            FROM clients c
            JOIN users u ON u.id = c.user_id
            WHERE c.user_id = :user_id AND c.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, u.name, u.email
            FROM clients c
            JOIN users u ON u.id = c.user_id
            WHERE c.id = :id AND c.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function getAllWithUsers(): array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, u.name, u.email, u.status AS user_status
            FROM clients c
            JOIN users u ON u.id = c.user_id
            WHERE c.deleted_at IS NULL AND u.deleted_at IS NULL
            ORDER BY u.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getAllWithDetails(): array
    {
        return $this->getAllWithUsers();
    }

    public function paginate(string $search = '', int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(5, min($perPage, 50));
        $where = 'WHERE c.deleted_at IS NULL AND u.deleted_at IS NULL';
        $params = [];

        if ($search !== '') {
            $where .= " AND (u.name LIKE :search_name OR u.email LIKE :search_email OR c.phone LIKE :search_phone)";
            $like = '%' . $search . '%';
            $params = [
                'search_name' => $like,
                'search_email' => $like,
                'search_phone' => $like,
            ];
        }

        $countStmt = $this->db->prepare("
            SELECT COUNT(*) FROM clients c
            JOIN users u ON u.id = c.user_id
            {$where}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare("
            SELECT c.*, u.name, u.email, u.status AS user_status
            FROM clients c
            JOIN users u ON u.id = c.user_id
            {$where}
            ORDER BY u.name ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    public function countForExport(string $search = ''): int
    {
        $where = "WHERE e.entity_scope='company' AND c.deleted_at IS NULL AND e.deleted_at IS NULL";
        $params = [];
        if ($search !== '') {
            $where .= ' AND (e.company_name LIKE :company OR u.name LIKE :name OR u.email LIKE :email OR c.phone LIKE :phone)';
            $like = '%' . $search . '%';
            $params = ['company'=>$like,'name'=>$like, 'email'=>$like, 'phone'=>$like];
        }
        $stmt=$this->db->prepare("SELECT COUNT(*) FROM client_entities e JOIN clients c ON c.id=e.client_id JOIN users u ON u.id=c.user_id {$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function getExportBatch(string $search, int $offset, int $limit = 250): array
    {
        $limit=max(1,min($limit,500));
        $offset=max(0,$offset);
        $where="WHERE e.entity_scope='company' AND c.deleted_at IS NULL AND e.deleted_at IS NULL";
        $params=[];
        if($search!==''){
            $where.=' AND (e.company_name LIKE :company OR u.name LIKE :name OR u.email LIKE :email OR c.phone LIKE :phone)';
            $like='%'.$search.'%';
            $params=['company'=>$like,'name'=>$like,'email'=>$like,'phone'=>$like];
        }
        $stmt=$this->db->prepare("
            SELECT c.id AS client_id,
                   COALESCE((SELECT ec.phone FROM entity_contacts ec WHERE ec.entity_id=e.id AND ec.is_primary=1 ORDER BY ec.id LIMIT 1),c.phone) AS phone,
                   c.address,c.aml_status,c.notes,u.name AS contact_name,
                   COALESCE((SELECT ec.email FROM entity_contacts ec WHERE ec.entity_id=e.id AND ec.is_primary=1 ORDER BY ec.id LIMIT 1),u.email) AS email,
                   u.status AS user_status,
                   e.id AS entity_id,e.company_name,e.company_number,e.tax_reference,e.attributes,
                   COALESCE(
                     (SELECT GROUP_CONCAT(ec.name ORDER BY ec.is_primary DESC,ec.id ASC SEPARATOR '; ') FROM entity_contacts ec WHERE ec.entity_id=e.id),
                     GROUP_CONCAT(DISTINCT du.name ORDER BY CASE WHEN du.id=c.user_id THEN 0 ELSE 1 END,du.name SEPARATOR '; ')
                   ) AS directors,
                   (SELECT d.due_date FROM deadlines d
                    WHERE d.entity_id=e.id AND d.type='Filing Deadline' AND d.deleted_at IS NULL
                    ORDER BY d.due_date ASC LIMIT 1) AS filing_deadline
            FROM client_entities e
            JOIN clients c ON c.id=e.client_id
            JOIN users u ON u.id=c.user_id
            LEFT JOIN entity_directors ed ON ed.entity_id=e.id
            LEFT JOIN users du ON du.id=ed.user_id
            {$where}
            GROUP BY c.id,c.phone,c.address,c.aml_status,c.notes,u.name,u.email,u.status,
                     e.id,e.company_name,e.company_number,e.tax_reference,e.attributes
            ORDER BY COALESCE(e.company_name,u.name) ASC,c.id ASC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getAmlActionRequiredCount(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*) AS total 
            FROM clients 
            WHERE aml_status = 'Action Required' AND deleted_at IS NULL
        ");
        $row = $stmt->fetch();
        return (int) ($row['total'] ?? 0);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO clients (user_id, phone, address, aml_status, notes)
            VALUES (:user_id, :phone, :address, :aml_status, :notes)
        ");
        $stmt->execute([
            'user_id'    => $data['user_id'],
            'phone'      => $data['phone'] ?? null,
            'address'    => $data['address'] ?? null,
            'aml_status' => $data['aml_status'] ?? 'Action Required',
            'notes'      => $data['notes'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): bool
    {
        return $this->softDelete($id);
    }

    public function softDelete(int $id): bool
    {
        $client = $this->findById($id);
        if (!$client) return false;

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("UPDATE clients SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute(['id' => $id]);
            if ($stmt->rowCount() !== 1) throw new \RuntimeException('The client record could not be moved to Trash.');
            if (!empty($client['user_id'])) {
                $this->db->prepare("UPDATE users SET deleted_at = NOW() WHERE id = :user_id")->execute(['user_id' => $client['user_id']]);
            }
            $this->db->prepare("UPDATE client_entities SET deleted_at = NOW() WHERE client_id = :client_id")->execute(['client_id' => $id]);
            $this->db->prepare("UPDATE documents SET deleted_at = NOW() WHERE client_id = :client_id")->execute(['client_id' => $id]);
            $this->db->prepare("UPDATE document_requests SET deleted_at = NOW() WHERE client_id = :client_id")->execute(['client_id' => $id]);
            $this->db->prepare("UPDATE messages SET deleted_at = NOW() WHERE client_id = :client_id")->execute(['client_id' => $id]);
            $this->db->prepare("UPDATE deadlines SET deleted_at = NOW() WHERE client_id = :client_id")->execute(['client_id' => $id]);
            $this->db->prepare("UPDATE meetings SET deleted_at = NOW() WHERE client_id = :client_id")->execute(['client_id' => $id]);
            if ($ownsTransaction) $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function bulkSoftDelete(array $ids): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if ($this->softDelete((int)$id)) {
                $count++;
            }
        }
        return $count;
    }

    public function restore(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT * FROM clients WHERE id = :id AND deleted_at IS NOT NULL LIMIT 1");
        $stmt->execute(['id' => $id]);
        $client = $stmt->fetch();
        if (!$client) return false;

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("UPDATE clients SET deleted_at = NULL WHERE id = :id AND deleted_at IS NOT NULL");
            $stmt->execute(['id' => $id]);
            if ($stmt->rowCount() !== 1) throw new \RuntimeException('The client record could not be restored.');
            if (!empty($client['user_id'])) {
                $this->db->prepare("UPDATE users SET deleted_at = NULL WHERE id = :user_id")->execute(['user_id' => $client['user_id']]);
            }
            $this->db->prepare("UPDATE client_entities SET deleted_at = NULL WHERE client_id = :client_id")->execute(['client_id' => $id]);
            $this->db->prepare("UPDATE documents SET deleted_at = NULL WHERE client_id = :client_id")->execute(['client_id' => $id]);
            $this->db->prepare("UPDATE document_requests SET deleted_at = NULL WHERE client_id = :client_id")->execute(['client_id' => $id]);
            $this->db->prepare("UPDATE messages SET deleted_at = NULL WHERE client_id = :client_id")->execute(['client_id' => $id]);
            $this->db->prepare("UPDATE deadlines SET deleted_at = NULL WHERE client_id = :client_id")->execute(['client_id' => $id]);
            $this->db->prepare("UPDATE meetings SET deleted_at = NULL WHERE client_id = :client_id")->execute(['client_id' => $id]);
            if ($ownsTransaction) $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function bulkRestore(array $ids): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if ($this->restore((int)$id)) {
                $count++;
            }
        }
        return $count;
    }

    public function getSoftDeleted(): array
    {
        $stmt = $this->db->query("
            SELECT c.*, u.name, u.email
            FROM clients c
            JOIN users u ON u.id = c.user_id
            WHERE c.deleted_at IS NOT NULL
            ORDER BY c.deleted_at DESC
        ");
        return $stmt->fetchAll();
    }
}
