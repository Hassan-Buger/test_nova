<?php

namespace Application\Controllers;

use Application\Config\App;
use Application\Core\Controller;
use Application\Core\Request;
use Application\Core\Response;
use Application\Models\SignatureAuditEvent;
use Application\Models\SignatureRequest;
use Application\Models\SignatureSigner;
use Application\Services\FileStorageService;
use Application\Services\SignatureService;
use Exception;
use Throwable;

class SigningController extends Controller
{
    /**
     * Display the document signing interface for a given token.
     */
    public function showSign(Request $request, Response $response, string $token): void
    {
        $ip = $request->getIp();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        try {
            $session = SignatureService::getSigningSession($token, $ip, $ua);
        } catch (Exception $e) {
            $code = $e->getCode() ?: 400;
            $this->render('signing/error', [
                'pageTitle' => 'Signature Request Notice',
                'message'   => $e->getMessage(),
                'code'      => $code,
            ], 'clean');
            return;
        }

        $signer = $session['signer'];
        $sigRequest = $session['request'];

        // If signer already completed, redirect to completed screen
        if ($signer['status'] === 'signed') {
            $response->redirect("/sign/{$token}/completed");
            return;
        }

        // If signer declined, redirect to declined screen
        if ($signer['status'] === 'declined') {
            $this->render('signing/declined', [
                'pageTitle'  => 'Document Declined',
                'request'    => $sigRequest,
                'signer'     => $signer,
            ], 'clean');
            return;
        }

        $this->render('signing/sign', [
            'pageTitle'   => 'Sign Document: ' . $sigRequest['title'],
            'token'       => $token,
            'request'     => $sigRequest,
            'signer'      => $signer,
            'myFields'    => $session['my_fields'],
            'allFields'   => $session['all_fields'],
            'isTurn'      => $session['is_turn'],
        ], 'clean');
    }

    /**
     * Submit completed fields and sign the document.
     */
    public function submitSign(Request $request, Response $response, string $token): void
    {
        $ip = $request->getIp();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $body = $request->getBody();
        $fields = $body['fields'] ?? [];

        if (!is_array($fields)) {
            $fields = json_decode((string)($body['fields_json'] ?? '[]'), true) ?: [];
        }

        try {
            $result = SignatureService::submitSignerFields($token, $fields, $ip, $ua);

            if ($request->isAjax()) {
                $response->json([
                    'success'       => true,
                    'all_completed' => $result['all_completed'],
                    'redirect_url'  => "/sign/{$token}/completed",
                ]);
                return;
            }

            $response->redirect("/sign/{$token}/completed");
        } catch (Throwable $e) {
            if ($request->isAjax()) {
                $response->json(['success' => false, 'message' => $e->getMessage()], 422);
                return;
            }

            $this->render('signing/error', [
                'pageTitle' => 'Signing Error',
                'message'   => $e->getMessage(),
                'code'      => 422,
            ], 'clean');
        }
    }

    /**
     * Decline a signature request.
     */
    public function decline(Request $request, Response $response, string $token): void
    {
        $ip = $request->getIp();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $reason = trim((string)($request->getBody()['reason'] ?? 'No reason provided'));

        try {
            SignatureService::declineRequest($token, $reason, $ip, $ua);

            if ($request->isAjax()) {
                $response->json(['success' => true, 'redirect_url' => "/sign/{$token}"]);
                return;
            }

            $response->redirect("/sign/{$token}");
        } catch (Throwable $e) {
            if ($request->isAjax()) {
                $response->json(['success' => false, 'message' => $e->getMessage()], 422);
                return;
            }

            $response->redirect("/sign/{$token}");
        }
    }

    /**
     * Post-signing completed celebration screen.
     */
    public function showCompleted(Request $request, Response $response, string $token): void
    {
        $signer = (new SignatureSigner())->findByToken($token);
        if (!$signer) {
            $response->redirect('/login');
            return;
        }

        $sigRequest = (new SignatureRequest())->find((int)$signer['request_id']);

        $this->render('signing/completed', [
            'pageTitle' => 'Signing Completed',
            'token'     => $token,
            'request'   => $sigRequest,
            'signer'    => $signer,
        ], 'clean');
    }

    /**
     * Securely download document for a valid signer token.
     */
    public function download(Request $request, Response $response, string $token): void
    {
        $signer = (new SignatureSigner())->findByToken($token);
        if (!$signer) {
            $response->setStatusCode(404);
            die('Invalid signature link.');
        }

        $sigRequest = (new SignatureRequest())->find((int)$signer['request_id']);
        if (!$sigRequest) {
            $response->setStatusCode(404);
            die('Document request not found.');
        }

        // If completed, provide the signed document; otherwise provide original
        $storedPath = (!empty($sigRequest['signed_stored_path'])) 
            ? $sigRequest['signed_stored_path'] 
            : $sigRequest['original_stored_path'];

        $filename = (!empty($sigRequest['signed_filename'])) 
            ? $sigRequest['signed_filename'] 
            : $sigRequest['original_filename'];

        $fullPath = FileStorageService::resolvePath((string)$storedPath, (string)$filename);
        if (!is_file($fullPath)) {
            $response->setStatusCode(404);
            $this->render('signing/error', [
                'pageTitle' => 'Document Unavailable',
                'message'   => 'The requested document copy could not be located in storage.',
                'code'      => 404,
            ], 'clean');
            return;
        }

        $safeFilename = str_replace(['"', "\r", "\n"], '', basename((string)$filename));
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Cache-Control: private, no-store, max-age=0');
        readfile($fullPath);
        exit;
    }

    /**
     * Stream PDF for inline rendering in the signing viewer.
     */
    public function streamPdf(Request $request, Response $response, string $token): void
    {
        $signer = (new SignatureSigner())->findByToken($token);
        if (!$signer) {
            $response->setStatusCode(404);
            $this->render('signing/error', [
                'pageTitle' => 'Link Invalid',
                'message'   => 'Invalid or non-existent signature link.',
                'code'      => 404,
            ], 'clean');
            return;
        }

        $sigRequest = (new SignatureRequest())->find((int)$signer['request_id']);
        if (!$sigRequest) {
            $response->setStatusCode(404);
            $this->render('signing/error', [
                'pageTitle' => 'Document Not Found',
                'message'   => 'The associated document request is no longer available.',
                'code'      => 404,
            ], 'clean');
            return;
        }

        $storedPath = (!empty($sigRequest['signed_stored_path'])) 
            ? $sigRequest['signed_stored_path'] 
            : $sigRequest['original_stored_path'];

        $filename = (!empty($sigRequest['signed_filename'])) 
            ? $sigRequest['signed_filename'] 
            : $sigRequest['original_filename'];

        $fullPath = FileStorageService::resolvePath((string)$storedPath, (string)$filename);
        if (!is_file($fullPath)) {
            $response->setStatusCode(404);
            $this->render('signing/error', [
                'pageTitle' => 'Document Unavailable',
                'message'   => 'The requested document could not be loaded for viewing.',
                'code'      => 404,
            ], 'clean');
            return;
        }

        $safeFilename = str_replace(['"', "\r", "\n"], '', basename((string)$filename));
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $safeFilename . '"');
        header('Content-Length: ' . filesize($fullPath));
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, no-store, max-age=0');
        readfile($fullPath);
        exit;
    }

    /**
     * Public verification page for completed signature certificates via QR code.
     */
    public function verify(Request $request, Response $response, string $qrToken): void
    {
        $sigReqModel = new SignatureRequest();
        $sigSignerModel = new SignatureSigner();
        $sigAuditModel = new SignatureAuditEvent();

        $sigRequest = $sigReqModel->findByQrToken($qrToken);
        if (!$sigRequest) {
            $this->render('signing/verify_not_found', [
                'pageTitle' => 'Certificate Not Found',
                'qrToken'   => $qrToken,
            ], 'clean');
            return;
        }

        $signers = $sigSignerModel->getByRequestId((int)$sigRequest['id']);
        $auditEvents = $sigAuditModel->getByRequestId((int)$sigRequest['id']);

        $this->render('signing/verify', [
            'pageTitle'   => 'Digital Signature Verification: ' . $sigRequest['title'],
            'request'     => $sigRequest,
            'signers'     => $signers,
            'auditEvents' => $auditEvents,
            'qrToken'     => $qrToken,
        ], 'clean');
    }
}
