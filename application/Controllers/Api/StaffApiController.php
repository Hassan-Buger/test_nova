<?php

namespace Application\Controllers\Api;

use Application\Core\Database;
use Application\Core\Request;
use Application\Core\Response;
use PDO;
use Throwable;

class StaffApiController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function index(Request $request, Response $response): void
    {
        $status = trim((string)$request->input('status', ''));
        $q = trim((string)$request->input('q', ''));

        $where = ["role = 'staff'", "deleted_at IS NULL"];
        $params = [];

        if ($status !== '') {
            $where[] = "status = :status";
            $params['status'] = $status;
        }

        if ($q !== '') {
            $where[] = "(name LIKE :q_name OR email LIKE :q_email)";
            $params['q_name'] = '%' . $q . '%';
            $params['q_email'] = '%' . $q . '%';
        }

        $whereSql = implode(' AND ', $where);

        $stmt = $this->db->prepare("
            SELECT id, name, email, role, status,
                   created_at, last_login_at
            FROM users
            WHERE {$whereSql}
            ORDER BY id ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if department column exists
        $hasDepartment = false;
        try {
            $colCheck = $this->db->query("SHOW COLUMNS FROM users LIKE 'department'");
            $hasDepartment = (bool)$colCheck->fetch();
        } catch (Throwable $e) {}

        if ($hasDepartment && !empty($rows)) {
            $deptStmt = $this->db->prepare("SELECT id, department FROM users WHERE id IN (" . implode(',', array_column($rows, 'id')) . ")");
            $deptStmt->execute();
            $deptMap = $deptStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($rows as &$row) {
                $row['department'] = $deptMap[$row['id']] ?? null;
            }
            unset($row);
        }

        // Standard departmental assignments if null
        $defaultDepts = [
            1 => 'Accounts & Audit',
            2 => 'Executive & Practice Management',
            3 => 'Tax & Statutory Compliance',
            4 => 'Client Operations & Bookkeeping'
        ];

        $data = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $dept = $row['department'] ?? ($defaultDepts[$id] ?? 'General Practice');
            $data[] = [
                'id'            => $id,
                'name'          => (string)$row['name'],
                'email'         => (string)$row['email'],
                'role'          => (string)$row['role'],
                'department'    => $dept,
                'status'        => (string)$row['status'],
                'created_at'    => gmdate('Y-m-d\TH:i:s\Z', strtotime($row['created_at'] ?? 'now')),
                'last_login_at' => !empty($row['last_login_at']) ? gmdate('Y-m-d\TH:i:s\Z', strtotime($row['last_login_at'])) : null
            ];
        }

        $response->json([
            'status' => 'success',
            'data'   => $data,
            'meta'   => [
                'total' => count($data)
            ]
        ], 200);
    }
}