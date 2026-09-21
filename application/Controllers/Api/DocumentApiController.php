<?php

namespace Application\Controllers\Api;

use Application\Config\App;
use Application\Core\Database;
use Application\Core\Request;
use Application\Core\Response;
use Application\Services\FileStorageService;
use PDO;
use Throwable;

class DocumentApiController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
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
            FROM documents
            WHERE entity_id = :entity_id AND deleted_at IS NULL
        ");
        $countStmt->execute(['entity_id' => $cleanId]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT *
            FROM documents
            WHERE entity_id = :entity_id AND deleted_at IS NULL
            ORDER BY created_at DESC, id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute(['entity_id' => $cleanId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $storageDir = App::get('storage_dir') ?: (dirname(__DIR__, 3) . '/storage');
        $uploadsDir = $storageDir . '/uploads';

        $data = [];
        foreach ($rows as $row) {
            $fullPath = $uploadsDir . '/' . basename($row['stored_path'] ?? '');
            $fileSize = (is_file($fullPath)) ? (int)filesize($fullPath) : 0;
            $createdAt = $row['created_at'] ? gmdate('Y-m-d\TH:i:s\Z', strtotime($row['created_at'])) : null;

            $data[] = [
                'id' => (int)$row['id'],
                'entity_id' => (int)$row['entity_id'],
                'company_id' => 'comp_' . $cleanId,
                'filename' => $row['filename'],
                'description' => $row['description'] ?? '',
                'direction' => $row['direction'],
                'status' => $row['status'] ?? 'Ready',
                'file_size' => $fileSize,
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

    public function generateAccessToken(Request $request, Response $response, string $id): void
    {
        $cleanId = (int)$id;
        if ($cleanId <= 0) {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'INVALID_DOCUMENT_ID',
                    'message' => 'The provided document ID is invalid.'
                ]
            ], 400);
            return;
        }

        $stmt = $this->db->prepare("
            SELECT id, filename, stored_path
            FROM documents
            WHERE id = :id AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['id' => $cleanId]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'DOCUMENT_NOT_FOUND',
                    'message' => "Document with ID {$id} was not found."
                ]
            ], 404);
            return;
        }

        $expiresIn = 900; // 15 minutes
        $expiresAt = time() + $expiresIn;
        $secret = App::get('secret');

        $dataToSign = "doc_{$cleanId}:{$expiresAt}";
        $sig = hash_hmac('sha256', $dataToSign, $secret);

        $payload = [
            'id' => $cleanId,
            'exp' => $expiresAt,
            'sig' => $sig
        ];

        $token = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $baseUrl = rtrim(App::get('url'), '/');
        $downloadUrl = $baseUrl . '/api/v1/documents/download?token=' . urlencode($token);

        $response->json([
            'status' => 'success',
            'data' => [
                'token' => $token,
                'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $expiresAt),
                'expires_in_seconds' => $expiresIn,
                'download_url' => $downloadUrl
            ]
        ], 200);
    }

    public function download(Request $request, Response $response): void
    {
        $token = trim((string)$request->input('token', ''));
        if ($token === '') {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'MISSING_DOWNLOAD_TOKEN',
                    'message' => 'A valid download token must be provided.'
                ]
            ], 401);
            return;
        }

        $decodedJson = base64_decode(strtr($token, '-_', '+/'));
        $data = json_decode($decodedJson, true);

        if (!is_array($data) || empty($data['id']) || empty($data['exp']) || empty($data['sig'])) {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'INVALID_DOWNLOAD_TOKEN',
                    'message' => 'The download token is invalid or malformed.'
                ]
            ], 401);
            return;
        }

        // Verify expiration
        if ((int)$data['exp'] < time()) {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'DOWNLOAD_TOKEN_EXPIRED',
                    'message' => 'The download token has expired.'
                ]
            ], 401);
            return;
        }

        // Verify HMAC signature
        $secret = App::get('secret');
        $dataToSign = "doc_{$data['id']}:{$data['exp']}";
        $expectedSig = hash_hmac('sha256', $dataToSign, $secret);

        if (!hash_equals($expectedSig, (string)$data['sig'])) {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'INVALID_DOWNLOAD_TOKEN',
                    'message' => 'The download token signature verification failed.'
                ]
            ], 401);
            return;
        }

        $docId = (int)$data['id'];
        $stmt = $this->db->prepare("
            SELECT * FROM documents
            WHERE id = :id AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute(['id' => $docId]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$document) {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'DOCUMENT_NOT_FOUND',
                    'message' => 'The requested document does not exist.'
                ]
            ], 404);
            return;
        }

        $filePath = FileStorageService::resolvePath((string)$document['stored_path'], (string)$document['filename']);
        if (!is_file($filePath)) {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'FILE_NOT_FOUND',
                    'message' => 'The requested file could not be located on disk.'
                ]
            ], 404);
            return;
        }

        $safeFilename = str_replace(['"', "\r", "\n"], '', basename((string)$document['filename']));
        $fileSize = filesize($filePath);
        $ext = strtolower(pathinfo($safeFilename, PATHINFO_EXTENSION));
        $contentType = ($ext === 'pdf') ? 'application/pdf' : 'application/octet-stream';

        // Clear output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
        header('Content-Length: ' . $fileSize);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-transform, no-store, must-revalidate');

        readfile($filePath);
        exit;
    }
}
