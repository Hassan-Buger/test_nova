<?php

namespace Application\Controllers\Staff;

use Application\Core\Controller;
use Application\Core\Request;
use Application\Core\Response;
use Application\Core\Session;
use Application\Models\Client;
use Application\Models\ClientEntity;
use Application\Models\Document;
use Application\Models\EntityAccess;
use Application\Models\SignatureAuditEvent;
use Application\Models\SignatureField;
use Application\Models\SignatureRequest;
use Application\Models\SignatureSigner;
use Application\Services\SignatureService;
use Exception;
use Throwable;

class SignatureController extends Controller
{
    private SignatureRequest $sigReqModel;
    private SignatureSigner $sigSignerModel;
    private SignatureField $sigFieldModel;
    private SignatureAuditEvent $sigAuditModel;

    public function __construct()
    {
        $this->sigReqModel = new SignatureRequest();
        $this->sigSignerModel = new SignatureSigner();
        $this->sigFieldModel = new SignatureField();
        $this->sigAuditModel = new SignatureAuditEvent();
    }

    /**
     * List signature requests.
     */
    public function index(Request $request, Response $response): void
    {
        $queryParams = $request->getQueryParams();
        $page = max(1, (int)($queryParams['page'] ?? 1));
        $filters = [
            'search'    => trim((string)($queryParams['search'] ?? '')),
            'status'    => trim((string)($queryParams['status'] ?? '')),
            'client_id' => !empty($queryParams['client_id']) ? (int)$queryParams['client_id'] : null,
        ];

        $result = $this->sigReqModel->paginateWithDetails($filters, $page, 15);
        $clients = (new Client())->getAllWithUsers();

        $this->render('staff/signatures/index', [
            'pageTitle' => 'Digital Signatures',
            'requests'  => $result['items'],
            'total'     => $result['total'],
            'page'      => $result['page'],
            'totalPages'=> $result['total_pages'],
            'filters'   => $filters,
            'clients'   => $clients,
        ], 'main');
    }

    /**
     * Show create signature request form.
     */
    public function showCreate(Request $request, Response $response): void
    {
        $queryParams = $request->getQueryParams();
        $selectedDocId = (int)($queryParams['document_id'] ?? 0);
        $selectedDoc = null;
        $suggestedSigners = [];

        $docModel = new Document();
        if ($selectedDocId > 0) {
            $selectedDoc = $docModel->find($selectedDocId);
            if ($selectedDoc) {
                $entityId = (int)($selectedDoc['entity_id'] ?? 0);
                if ($entityId > 0) {
                    $entityAccess = new EntityAccess();
                    $directors = $entityAccess->directors($entityId);
                    $contacts = $entityAccess->contacts($entityId);
                    foreach ($directors as $d) {
                        $suggestedSigners[] = [
                            'name'  => $d['name'],
                            'email' => $d['email'],
                            'role'  => 'signer',
                            'type'  => 'Director',
                        ];
                    }
                    foreach ($contacts as $c) {
                        if (!empty($c['email'])) {
                            $suggestedSigners[] = [
                                'name'  => $c['name'],
                                'email' => $c['email'],
                                'role'  => 'signer',
                                'type'  => 'Contact',
                            ];
                        }
                    }
                }
            }
        }

        // Get available PDF documents
        $allDocs = $docModel->getAllWithDetails();
        $pdfDocs = array_filter($allDocs, function ($d) {
            return strtolower(pathinfo((string)$d['filename'], PATHINFO_EXTENSION)) === 'pdf';
        });

        $clients = (new Client())->getAllWithUsers();

        $this->render('staff/signatures/create', [
            'pageTitle'        => 'Request Digital Signature',
            'selectedDoc'      => $selectedDoc,
            'pdfDocs'          => $pdfDocs,
            'clients'          => $clients,
            'suggestedSigners' => $suggestedSigners,
        ], 'main');
    }

    /**
     * Store new signature request and send invites.
     */
    public function processCreate(Request $request, Response $response): void
    {
        $staffUserId = (int)Session::get('user_id');
        $body = $request->getBody();

        $docId = (int)($body['document_id'] ?? 0);
        $title = trim((string)($body['title'] ?? ''));
        $signingOrder = ($body['signing_order'] ?? 'parallel') === 'sequential' ? 'sequential' : 'parallel';
        $expiresAt = !empty($body['expires_at']) ? $body['expires_at'] . ' 23:59:59' : null;

        // Parse signers array (handles JSON or form input)
        $signers = $body['signers'] ?? [];
        if (is_string($signers)) {
            $signers = json_decode($signers, true) ?: [];
        }

        // Parse fields array
        $fields = $body['fields'] ?? [];
        if (is_string($fields)) {
            $fields = json_decode($fields, true) ?: [];
        }

        try {
            if ($docId <= 0) {
                throw new Exception("Please select a document.");
            }

            if (empty($signers)) {
                throw new Exception("At least one signer is required.");
            }

            $requestId = SignatureService::createRequest([
                'document_id'            => $docId,
                'title'                  => $title,
                'signing_order'          => $signingOrder,
                'expires_at'             => $expiresAt,
                'send_now'               => !empty($body['send_now']),
                'allow_drawn_signature'  => 1,
                'allow_typed_signature'  => 1,
                'allow_upload_signature' => 1,
            ], $signers, $fields, $staffUserId);

            if ($request->isAjax()) {
                $response->json([
                    'success'      => true,
                    'request_id'   => $requestId,
                    'redirect_url' => "/staff/signatures/{$requestId}",
                ]);
                return;
            }

            Session::setFlash('success', "Signature request '{$title}' created successfully.");
            $response->redirect("/staff/signatures/{$requestId}");
        } catch (Throwable $e) {
            if ($request->isAjax()) {
                $response->json(['success' => false, 'message' => $e->getMessage()], 422);
                return;
            }

            Session::setFlash('error', $e->getMessage());
            $response->redirect('/staff/signatures/create?document_id=' . $docId);
        }
    }

    /**
     * Show detailed view of a signature request and its audit timeline.
     */
    public function show(Request $request, Response $response, int $id): void
    {
        $sigRequest = $this->sigReqModel->find($id);
        if (!$sigRequest) {
            Session::setFlash('error', 'Signature request not found.');
            $response->redirect('/staff/signatures');
            return;
        }

        $haveAllSigned = $this->sigSignerModel->haveAllSigned($id);

        // Self-healing: if all parties have completed signing but request is still pending,
        // automatically seal the document so staff immediately sees the signed PDF.
        if ($haveAllSigned && $sigRequest['status'] === 'pending') {
            try {
                \Application\Services\SignatureSealerService::seal($id);
                $sigRequest = $this->sigReqModel->find($id) ?: $sigRequest;
            } catch (Throwable $e) {
                error_log("Auto-seal for signature request #{$id} deferred: " . $e->getMessage());
            }
        }

        $signers = $this->sigSignerModel->getByRequestId($id);
        $fields = $this->sigFieldModel->getByRequestId($id);
        $auditEvents = $this->sigAuditModel->getByRequestId($id);

        $this->render('staff/signatures/show', [
            'pageTitle'     => 'Signature Request #' . $id . ': ' . ($sigRequest['title'] ?? 'Document'),
            'request'       => $sigRequest,
            'signers'       => $signers,
            'fields'        => $fields,
            'auditEvents'   => $auditEvents,
            'haveAllSigned' => $haveAllSigned,
        ], 'main');
    }

    /**
     * Manually finalize and seal an executed signature request.
     */
    public function seal(Request $request, Response $response): void
    {
        $requestId = (int)($request->getBody()['request_id'] ?? 0);
        if ($requestId <= 0) {
            $msg = 'Invalid signature request ID.';
            if ($request->isAjax()) {
                $response->json(['success' => false, 'message' => $msg], 422);
                return;
            }
            Session::setFlash('error', $msg);
            $response->redirect('/staff/signatures');
            return;
        }

        try {
            \Application\Services\SignatureSealerService::seal($requestId);
            $msg = 'Document successfully sealed and signed PDF generated.';
            if ($request->isAjax()) {
                $response->json(['success' => true, 'message' => $msg]);
                return;
            }
            Session::setFlash('success', $msg);
        } catch (Throwable $e) {
            $msg = 'Failed to seal document: ' . $e->getMessage();
            if ($request->isAjax()) {
                $response->json(['success' => false, 'message' => $msg], 422);
                return;
            }
            Session::setFlash('error', $msg);
        }

        $response->redirect('/staff/signatures/' . $requestId);
    }

    /**
     * Cancel an active signature request.
     */
    public function cancel(Request $request, Response $response): void
    {
        $staffUserId = (int)Session::get('user_id');
        $requestId = (int)($request->getBody()['request_id'] ?? 0);

        if ($requestId > 0 && SignatureService::cancelRequest($requestId, $staffUserId)) {
            $msg = 'Signature request cancelled.';
            if ($request->isAjax()) {
                $response->json(['success' => true, 'message' => $msg]);
                return;
            }
            Session::setFlash('success', $msg);
        } else {
            $msg = 'Failed to cancel signature request.';
            if ($request->isAjax()) {
                $response->json(['success' => false, 'message' => $msg], 422);
                return;
            }
            Session::setFlash('error', $msg);
        }

        $response->redirect('/staff/signatures');
    }

    /**
     * Resend an invitation email to a specific signer.
     */
    public function resend(Request $request, Response $response): void
    {
        $signerId = (int)($request->getBody()['signer_id'] ?? 0);
        $signer = $this->sigSignerModel->find($signerId);

        if (!$signer) {
            $msg = 'Signer not found.';
            if ($request->isAjax()) {
                $response->json(['success' => false, 'message' => $msg], 404);
                return;
            }
            Session::setFlash('error', $msg);
            $response->redirect('/staff/signatures');
            return;
        }

        $sigRequest = $this->sigReqModel->find((int)$signer['request_id']);
        SignatureService::sendSingleSignerInvite($sigRequest, $signer);

        $this->sigAuditModel->log(
            (int)$sigRequest['id'],
            'invite_resent',
            "Invitation email resent to {$signer['name']} ({$signer['email']}).",
            $signerId,
            (int)Session::get('user_id')
        );

        $msg = "Invitation resent to {$signer['email']}.";
        if ($request->isAjax()) {
            $response->json(['success' => true, 'message' => $msg]);
            return;
        }
        Session::setFlash('success', $msg);
        $response->redirect('/staff/signatures/' . $sigRequest['id']);
    }
}
