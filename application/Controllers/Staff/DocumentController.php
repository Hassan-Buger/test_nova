<?php

namespace Application\Controllers\Staff;

use Application\Config\App;
use Application\Core\Controller;
use Application\Core\Request;
use Application\Core\Response;
use Application\Core\Session;
use Application\Models\Client;
use Application\Models\Document;
use Application\Models\Notification;
use Application\Models\ClientEntity;
use Application\Models\EntityAccess;
use Application\Services\AuditService;
use Application\Services\FileStorageService;

class DocumentController extends Controller
{
    private Document $documentModel;
    private Client $clientModel;

    public function __construct()
    {
        $this->documentModel = new Document();
        $this->clientModel   = new Client();
    }

    public function index(Request $request, Response $response): void
    {
        $query = $request->getQueryParams();
        $statuses = $this->documentModel->getStatuses();
        $requestedStatus = trim((string) ($query['status'] ?? ''));
        $filters = [
            'search' => trim((string) ($query['q'] ?? '')),
            'client_id' => max(0, (int) ($query['client_id'] ?? 0)),
            'direction' => in_array(($query['direction'] ?? ''), ['client_upload', 'from_trinova'], true) ? $query['direction'] : '',
            'status' => in_array($requestedStatus, $statuses, true) ? $requestedStatus : '',
            'file_type' => in_array(($query['file_type'] ?? ''), ['pdf', 'word', 'spreadsheet', 'image', 'archive', 'text', 'other'], true) ? $query['file_type'] : '',
            'date_from' => $this->validDate($query['date_from'] ?? '') ? $query['date_from'] : '',
            'date_to' => $this->validDate($query['date_to'] ?? '') ? $query['date_to'] : '',
            'sort' => in_array(($query['sort'] ?? ''), ['newest', 'oldest', 'client_asc', 'filename_asc'], true) ? $query['sort'] : 'newest',
        ];
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 20);
        if (!in_array($perPage, [10, 20, 50], true)) {
            $perPage = 20;
        }
        $pagination = $this->documentModel->paginateWithDetails($filters, $page, $perPage);
        $clients   = $this->clientModel->getAllWithUsers();
        $entities  = (new ClientEntity())->getAllWithClient();

        $this->render('staff/documents/index', [
            'pageTitle' => 'Document Management',
            'documents' => $pagination['items'],
            'clients'   => $clients,
            'entities'  => $entities,
            'statuses' => $statuses,
            'filters' => $filters,
            'pagination' => $pagination,
        ], 'main');
    }

    private function validDate(mixed $value): bool
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    public function upload(Request $request, Response $response): void
    {
        $staffUserId = Session::get('user_id');
        $entityId    = (int)($request->getBody()['entity_id'] ?? 0);
        $entity      = $entityId > 0 ? (new ClientEntity())->findById($entityId) : null;
        $clientId    = (int)($entity['client_id'] ?? 0);
        $description = trim($request->getBody()['description'] ?? '');

        if ($clientId <= 0 || empty($_FILES['file']['name'])) {
            Session::setFlash('error', 'Please select a client and choose a file to dispatch.');
            $response->redirect('/staff/documents');
            return;
        }

        try {
            $stored = FileStorageService::store($_FILES['file']);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if ($request->isAjax()) $response->json(['success' => false, 'message' => $message], 422);
            Session::setFlash('error', $message);
            $response->redirect('/staff/documents');
            return;
        }

        $filename = $stored['original_filename'];
        try {
            $docId = $this->documentModel->create([
                'client_id'           => $clientId,
                'entity_id'           => $entityId,
                'scope'               => $entity['entity_scope'],
                'uploaded_by_user_id' => $staffUserId,
                'direction'           => 'from_trinova',
                'filename'            => $filename,
                'stored_path'         => $stored['stored_path'],
                'description'         => $description,
                'status'              => 'Ready',
            ]);
        } catch (\Throwable $e) {
            FileStorageService::remove($stored['stored_path']);
            throw $e;
        }

        AuditService::log('staff_upload', 'documents', $docId);
        try {
            foreach ((new EntityAccess())->recipients($entityId) as $client) {
                (new Notification())->create(
                    (int)$client['id'],
                    'document_received',
                    'document:' . $docId,
                    'New Document Available',
                    "A new document has been uploaded to your account: {$filename}",
                    '/client/documents/trinova'
                );
            }
        } catch (\Throwable $e) {
            // Notifications must never prevent a successful document upload.
        }
        Session::setFlash('success', "Document '{$filename}' dispatched to client account successfully.");
        $response->redirect('/staff/documents');
    }

    public function download(Request $request, Response $response, int $id): void
    {
        $doc = $this->documentModel->find($id);
        if (!$doc) {
            $response->setStatusCode(404);
            die('Document Not Found.');
        }

        $filePath = FileStorageService::resolvePath((string)$doc['stored_path'], (string)$doc['filename']);
        if (!file_exists($filePath)) {
            $response->setStatusCode(404);
            die('File artifact missing from storage directory.');
        }

        if ((string)$request->input('preview', '') === '1') {
            $extension = strtolower(pathinfo((string)$doc['filename'], PATHINFO_EXTENSION));
            $contentTypes = [
                'pdf' => 'application/pdf',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'txt' => 'text/plain; charset=utf-8',
                'csv' => 'text/csv; charset=utf-8',
            ];

            // Unsupported browser-preview formats continue as downloads.
            if (isset($contentTypes[$extension])) {
                AuditService::log('view', 'documents', $doc['id']);
                $safeFilename = str_replace(['"', "\r", "\n"], '', basename((string)$doc['filename']));
                header('Content-Type: ' . $contentTypes[$extension]);
                header('Content-Disposition: inline; filename="' . $safeFilename . '"');
                header('Content-Length: ' . filesize($filePath));
                header('Cache-Control: private, no-store, max-age=0');
                header('X-Content-Type-Options: nosniff');
                readfile($filePath);
                exit;
            }
        }

        AuditService::log('download', 'documents', $doc['id']);

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($doc['filename']) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    public function delete(Request $request, Response $response): void
    {
        $docId = (int)($request->input('document_id', 0) ?: $request->input('id', 0));
        if ($docId > 0 && $this->documentModel->softDelete($docId)) {
            AuditService::log('document_deleted', 'documents', $docId);
            $msg = 'Document deleted successfully.';
            if ($request->isAjax()) {
                $response->json(['success' => true, 'message' => $msg]);
                return;
            }
            Session::setFlash('success', $msg);
        } else {
            if ($request->isAjax()) {
                $response->json(['success' => false, 'message' => 'Failed to delete document.'], 422);
                return;
            }
            Session::setFlash('error', 'Failed to delete document.');
        }
        $response->redirect('/staff/documents');
    }

    public function bulkDelete(Request $request, Response $response): void
    {
        $rawIds = $request->input('ids', []);
        $ids = is_array($rawIds) ? array_map('intval', array_filter($rawIds)) : [];

        if (empty($ids)) {
            if ($request->isAjax()) {
                $response->json(['success' => false, 'message' => 'No documents selected for deletion.'], 422);
                return;
            }
            Session::setFlash('error', 'No documents selected for deletion.');
            $response->redirect('/staff/documents');
            return;
        }

        $count = $this->documentModel->bulkSoftDelete($ids);
        if ($count > 0) {
            AuditService::log('document_bulk_deleted', 'documents', null, null, ['count' => $count, 'ids' => $ids]);
            $msg = "{$count} document(s) deleted successfully.";
            if ($request->isAjax()) {
                $response->json(['success' => true, 'message' => $msg]);
                return;
            }
            Session::setFlash('success', $msg);
        }
        $response->redirect('/staff/documents');
    }
}
