<?php

namespace Application\Controllers\Api;

require_once __DIR__ . '/../../Services/IdempotencyService.php';
require_once __DIR__ . '/../../Services/WebhookService.php';

use Application\Core\Database;
use Application\Core\Request;
use Application\Core\Response;
use Application\Services\IdempotencyService;
use Application\Services\WebhookService;
use PDO;
use Throwable;

class TaskApiController
{
    private PDO $db;
    private IdempotencyService $idempotency;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->idempotency = new IdempotencyService($this->db);
    }

    /**
     * GET /api/v1/tasks?updated_since=&page=&per_page=
     * Returns tasks created or changed since updated_since (includes cancelled and deleted tasks).
     */
    public function index(Request $request, Response $response): void
    {
        $updatedSince = trim((string)$request->input('updated_since', ''));
        $page = max(1, (int)$request->input('page', 1));
        $perPage = max(1, min((int)$request->input('per_page', 20), 100));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        if ($updatedSince !== '') {
            $parsedTimestamp = strtotime($updatedSince);
            if ($parsedTimestamp !== false) {
                $where[] = "t.updated_at >= :updated_since";
                $params['updated_since'] = gmdate('Y-m-d H:i:s', $parsedTimestamp);
            }
        }

        $clientId = $request->input('client_id');
        if (!empty($clientId)) {
            $cleanClientId = (int)str_replace('comp_', '', (string)$clientId);
            if ($cleanClientId > 0) {
                $where[] = "t.client_id = :client_id";
                $params['client_id'] = $cleanClientId;
            }
        }

        $status = trim((string)$request->input('status', ''));
        if ($status !== '') {
            $where[] = "t.status = :status";
            $params['status'] = $status;
        }

        $externalRef = trim((string)$request->input('external_ref', ''));
        if ($externalRef !== '') {
            $where[] = "t.external_ref = :external_ref";
            $params['external_ref'] = $externalRef;
        }

        $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total count
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM tasks t {$whereSql}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Paginated tasks
        $stmt = $this->db->prepare("
            SELECT t.id,
                   t.client_id,
                   t.title,
                   t.description,
                   t.status,
                   t.priority,
                   t.due_date,
                   t.assignee_id,
                   t.department,
                   t.external_ref,
                   t.is_blocked,
                   t.blocked_reason,
                   t.is_deleted,
                   t.created_at,
                   t.updated_at,
                   u.name AS assignee_name,
                   u.email AS assignee_email
            FROM tasks t
            LEFT JOIN users u ON u.id = t.assignee_id
            {$whereSql}
            ORDER BY t.updated_at ASC, t.id ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_map([$this, 'formatTaskRecord'], $rows);

        $response->json([
            'status' => 'success',
            'data'   => $data,
            'meta'   => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage
            ]
        ], 200);
    }

    /**
     * POST /api/v1/tasks (with Idempotency-Key)
     */
    public function create(Request $request, Response $response): void
    {
        $payload = $request->getJsonBody();
        if (empty($payload)) {
            $payload = $request->getBody();
        }

        $idempotencyKey = $request->getHeader('Idempotency-Key');
        if (!empty($idempotencyKey)) {
            $cached = $this->idempotency->get($idempotencyKey);
            if ($cached !== null) {
                $response->json($cached['body'], $cached['status']);
                return;
            }
        }

        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code'    => 'VALIDATION_FAILED',
                    'message' => 'The task title is required.'
                ]
            ], 422);
            return;
        }

        // Clean client_id
        $rawClientId = $payload['client_id'] ?? ($payload['company_id'] ?? null);
        $clientId = null;
        if (!empty($rawClientId)) {
            $clientId = (int)str_replace('comp_', '', (string)$rawClientId);
            if ($clientId <= 0) {
                $clientId = null;
            }
        }

        $description = isset($payload['description']) ? trim((string)$payload['description']) : null;
        $status = strtolower(trim((string)($payload['status'] ?? 'open')));
        if ($status === 'done') $status = 'completed';
        if ($status === 'pending') $status = 'open';
        if (!in_array($status, ['open', 'in_progress', 'under_review', 'completed', 'cancelled'], true)) {
            $status = 'open';
        }

        $priority = strtolower(trim((string)($payload['priority'] ?? 'medium')));
        if ($priority === 'normal') $priority = 'medium';
        if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            $priority = 'medium';
        }

        $dueDate = !empty($payload['due_date']) ? date('Y-m-d', strtotime((string)$payload['due_date'])) : null;
        $assigneeId = !empty($payload['assignee_id']) ? (int)$payload['assignee_id'] : null;
        $department = isset($payload['department']) ? trim((string)$payload['department']) : null;
        $externalRef = isset($payload['external_ref']) ? trim((string)$payload['external_ref']) : null;
        $isBlocked = !empty($payload['is_blocked']) ? 1 : 0;
        $blockedReason = isset($payload['blocked_reason']) ? trim((string)$payload['blocked_reason']) : null;
        $isDeleted = !empty($payload['is_deleted']) ? 1 : 0;

        $stmt = $this->db->prepare("
            INSERT INTO tasks (
                client_id, title, description, status, priority,
                due_date, assignee_id, department, external_ref,
                is_blocked, blocked_reason, is_deleted, created_at, updated_at
            ) VALUES (
                :client_id, :title, :description, :status, :priority,
                :due_date, :assignee_id, :department, :external_ref,
                :is_blocked, :blocked_reason, :is_deleted, NOW(), NOW()
            )
        ");
        $stmt->execute([
            'client_id'      => $clientId,
            'title'          => $title,
            'description'    => $description,
            'status'         => $status,
            'priority'       => $priority,
            'due_date'       => $dueDate,
            'assignee_id'    => $assigneeId,
            'department'     => $department,
            'external_ref'   => $externalRef,
            'is_blocked'     => $isBlocked,
            'blocked_reason' => $blockedReason,
            'is_deleted'     => $isDeleted
        ]);

        $taskId = (int)$this->db->lastInsertId();
        $task = $this->findTaskById($taskId);
        if (!$task) {
            $response->json([
                'status' => 'error',
                'error'  => ['code' => 'INTERNAL_ERROR', 'message' => 'Failed to retrieve created task.']
            ], 500);
            return;
        }

        $formatted = $this->formatTaskRecord($task);
        $resBody = [
            'status' => 'success',
            'data'   => $formatted
        ];

        // Idempotency caching
        if (!empty($idempotencyKey)) {
            $this->idempotency->save(
                $idempotencyKey,
                'POST',
                '/api/v1/tasks',
                md5(json_encode($payload)),
                201,
                $resBody
            );
        }

        // Webhook dispatch (asynchronous / non-blocking)
        WebhookService::dispatch('task.updated', $formatted);

        $response->json($resBody, 201);
    }

    /**
     * PATCH /api/v1/tasks/:id
     */
    public function update(Request $request, Response $response, string $id): void
    {
        $taskId = (int)$id;
        if ($taskId <= 0) {
            $response->json([
                'status' => 'error',
                'error'  => ['code' => 'INVALID_ID', 'message' => 'Valid numeric task ID required.']
            ], 400);
            return;
        }

        $existing = $this->findTaskById($taskId);
        if (!$existing) {
            $response->json([
                'status' => 'error',
                'error'  => ['code' => 'TASK_NOT_FOUND', 'message' => "Task #{$taskId} does not exist."]
            ], 404);
            return;
        }

        $payload = $request->getJsonBody();
        if (empty($payload)) {
            $payload = $request->getBody();
        }

        $updates = [];
        $params = ['id' => $taskId];

        if (array_key_exists('title', $payload)) {
            $title = trim((string)$payload['title']);
            if ($title === '') {
                $response->json([
                    'status' => 'error',
                    'error'  => ['code' => 'VALIDATION_FAILED', 'message' => 'Title cannot be empty.']
                ], 422);
                return;
            }
            $updates[] = "title = :title";
            $params['title'] = $title;
        }

        if (array_key_exists('description', $payload)) {
            $updates[] = "description = :description";
            $params['description'] = !empty($payload['description']) ? trim((string)$payload['description']) : null;
        }

        if (array_key_exists('status', $payload)) {
            $status = strtolower(trim((string)$payload['status']));
            if ($status === 'done') $status = 'completed';
            if ($status === 'pending') $status = 'open';
            if (in_array($status, ['open', 'in_progress', 'under_review', 'completed', 'cancelled'], true)) {
                $updates[] = "status = :status";
                $params['status'] = $status;
            }
        }

        if (array_key_exists('priority', $payload)) {
            $priority = strtolower(trim((string)$payload['priority']));
            if ($priority === 'normal') $priority = 'medium';
            if (in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
                $updates[] = "priority = :priority";
                $params['priority'] = $priority;
            }
        }

        if (array_key_exists('due_date', $payload)) {
            $updates[] = "due_date = :due_date";
            $params['due_date'] = !empty($payload['due_date']) ? date('Y-m-d', strtotime((string)$payload['due_date'])) : null;
        }

        if (array_key_exists('assignee_id', $payload)) {
            $updates[] = "assignee_id = :assignee_id";
            $params['assignee_id'] = !empty($payload['assignee_id']) ? (int)$payload['assignee_id'] : null;
        }

        if (array_key_exists('department', $payload)) {
            $updates[] = "department = :department";
            $params['department'] = !empty($payload['department']) ? trim((string)$payload['department']) : null;
        }

        if (array_key_exists('external_ref', $payload)) {
            $updates[] = "external_ref = :external_ref";
            $params['external_ref'] = !empty($payload['external_ref']) ? trim((string)$payload['external_ref']) : null;
        }

        if (array_key_exists('is_blocked', $payload)) {
            $updates[] = "is_blocked = :is_blocked";
            $params['is_blocked'] = !empty($payload['is_blocked']) ? 1 : 0;
        }

        if (array_key_exists('blocked_reason', $payload)) {
            $updates[] = "blocked_reason = :blocked_reason";
            $params['blocked_reason'] = !empty($payload['blocked_reason']) ? trim((string)$payload['blocked_reason']) : null;
        }

        if (array_key_exists('is_deleted', $payload)) {
            $isDel = !empty($payload['is_deleted']) ? 1 : 0;
            $updates[] = "is_deleted = :is_deleted";
            $params['is_deleted'] = $isDel;
            if ($isDel === 1) {
                $updates[] = "deleted_at = NOW()";
            } else {
                $updates[] = "deleted_at = NULL";
            }
        }

        // Always update updated_at timestamp
        $updates[] = "updated_at = NOW()";

        $sql = "UPDATE tasks SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $fresh = $this->findTaskById($taskId);
        $formatted = $this->formatTaskRecord($fresh);

        // Webhook dispatch
        WebhookService::dispatch('task.updated', $formatted);

        $response->json([
            'status' => 'success',
            'data'   => $formatted
        ], 200);
    }

    /**
     * POST /api/v1/tasks/:id/complete
     */
    public function complete(Request $request, Response $response, string $id): void
    {
        $taskId = (int)$id;
        $existing = $this->findTaskById($taskId);
        if (!$existing) {
            $response->json([
                'status' => 'error',
                'error'  => ['code' => 'NOT_FOUND', 'message' => "Task #{$taskId} not found."]
            ], 404);
            return;
        }

        $stmt = $this->db->prepare("UPDATE tasks SET status = 'completed', updated_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $taskId]);

        $fresh = $this->findTaskById($taskId);
        $formatted = $this->formatTaskRecord($fresh);

        WebhookService::dispatch('task.updated', $formatted);

        $response->json([
            'status'  => 'success',
            'data'    => $formatted,
            'message' => 'Task marked as completed.'
        ], 200);
    }

    /**
     * DELETE /api/v1/tasks/:id
     */
    public function delete(Request $request, Response $response, string $id): void
    {
        $taskId = (int)$id;
        $existing = $this->findTaskById($taskId);
        if (!$existing) {
            $response->json([
                'status' => 'error',
                'error'  => ['code' => 'NOT_FOUND', 'message' => "Task #{$taskId} not found."]
            ], 404);
            return;
        }

        $stmt = $this->db->prepare("UPDATE tasks SET is_deleted = 1, deleted_at = NOW(), updated_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $taskId]);

        $fresh = $this->findTaskById($taskId);
        $formatted = $this->formatTaskRecord($fresh);

        WebhookService::dispatch('task.updated', $formatted);

        $response->json([
            'status'  => 'success',
            'message' => 'Task deleted.'
        ], 200);
    }

    private function findTaskById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, u.name AS assignee_name, u.email AS assignee_email
            FROM tasks t
            LEFT JOIN users u ON u.id = t.assignee_id
            WHERE t.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function formatTaskRecord(array $row): array
    {
        $cId = !empty($row['client_id']) ? (int)$row['client_id'] : null;
        return [
            'id'             => (int)$row['id'],
            'client_id'      => $cId,
            'company_id'     => $cId ? 'comp_' . $cId : null,
            'title'          => (string)$row['title'],
            'description'    => $row['description'] !== null ? (string)$row['description'] : null,
            'status'         => (string)$row['status'],
            'priority'       => (string)$row['priority'],
            'due_date'       => !empty($row['due_date']) ? date('Y-m-d', strtotime($row['due_date'])) : null,
            'assignee_id'    => !empty($row['assignee_id']) ? (int)$row['assignee_id'] : null,
            'assignee_name'  => !empty($row['assignee_name']) ? (string)$row['assignee_name'] : null,
            'assignee_email' => !empty($row['assignee_email']) ? (string)$row['assignee_email'] : null,
            'department'     => !empty($row['department']) ? (string)$row['department'] : null,
            'external_ref'   => !empty($row['external_ref']) ? (string)$row['external_ref'] : null,
            'is_blocked'     => (int)($row['is_blocked'] ?? 0),
            'blocked_reason' => !empty($row['blocked_reason']) ? (string)$row['blocked_reason'] : null,
            'is_deleted'     => (int)($row['is_deleted'] ?? 0),
            'created_at'     => gmdate('Y-m-d\TH:i:s\Z', strtotime($row['created_at'] ?? 'now')),
            'updated_at'     => gmdate('Y-m-d\TH:i:s\Z', strtotime($row['updated_at'] ?? 'now'))
        ];
    }
}