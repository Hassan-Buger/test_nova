<?php

namespace Application\Services;

use Application\Config\App;
use Application\Core\Database;
use Application\Models\Document;
use Application\Models\EntityAccess;
use Application\Models\Notification;
use Application\Models\SignatureAuditEvent;
use Application\Models\SignatureField;
use Application\Models\SignatureRequest;
use Application\Models\SignatureSigner;
use Application\Models\User;
use Exception;
use Throwable;

class SignatureService
{
    /**
     * Determine whether it is a specific signer's turn in a given request.
     */
    public static function isSignerTurn(array $signer, array $request, array $allSigners): bool
    {
        $order = $request['signing_order'] ?? 'parallel';
        if ($order !== 'sequential') {
            return true;
        }

        $myOrder = (int)($signer['signing_order'] ?? 1);
        foreach ($allSigners as $other) {
            $otherOrder = (int)($other['signing_order'] ?? 1);
            $role = $other['role'] ?? 'signer';
            $status = $other['status'] ?? 'pending';
            if ($otherOrder < $myOrder && in_array($role, ['signer', 'approver'], true) && $status !== 'signed') {
                return false;
            }
        }
        return true;
    }

    /**
     * Create a new signature request with signers and fields.
     */
    public static function createRequest(array $data, array $signersData, array $fieldsData, int $staffUserId): int
    {
        $docModel = new Document();
        $docId = (int)$data['document_id'];
        $doc = $docModel->find($docId);

        if (!$doc) {
            throw new Exception("Selected document not found.");
        }

        $extension = strtolower(pathinfo((string)$doc['filename'], PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            throw new Exception("Digital signatures can only be requested on PDF documents.");
        }

        $entityAccess = new EntityAccess();
        $clientId = (int)$doc['client_id'];
        $entityId = !empty($doc['entity_id']) ? (int)$doc['entity_id'] : null;

        if (empty($signersData)) {
            throw new Exception("At least one signer is required.");
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $sigReqModel = new SignatureRequest();
            $sigSignerModel = new SignatureSigner();
            $sigFieldModel = new SignatureField();
            $sigAuditModel = new SignatureAuditEvent();

            $requestId = $sigReqModel->create([
                'document_id'             => $docId,
                'client_id'               => $clientId,
                'entity_id'               => $entityId,
                'created_by_user_id'      => $staffUserId,
                'title'                   => trim((string)($data['title'] ?? $doc['filename'])),
                'status'                  => !empty($data['send_now']) ? 'pending' : 'draft',
                'signing_order'           => $data['signing_order'] ?? 'parallel',
                'allow_drawn_signature'   => isset($data['allow_drawn_signature']) ? (int)(bool)$data['allow_drawn_signature'] : 1,
                'allow_typed_signature'   => isset($data['allow_typed_signature']) ? (int)(bool)$data['allow_typed_signature'] : 1,
                'allow_upload_signature'  => isset($data['allow_upload_signature']) ? (int)(bool)$data['allow_upload_signature'] : 1,
                'expires_at'              => !empty($data['expires_at']) ? $data['expires_at'] : null,
            ]);

            // Save signers
            $createdSigners = [];
            foreach ($signersData as $index => $s) {
                $email = strtolower(trim((string)$s['email']));
                $name = trim((string)$s['name']);

                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Valid email address is required for all signers.");
                }

                // Match with registered TriNova user if available
                $matchedUser = (new User())->findByEmail($email);
                $userId = $matchedUser ? (int)$matchedUser['id'] : null;

                $order = isset($s['signing_order']) ? max(1, (int)$s['signing_order']) : ($index + 1);

                $signerId = $sigSignerModel->create([
                    'request_id'    => $requestId,
                    'user_id'       => $userId,
                    'name'          => $name ?: explode('@', $email)[0],
                    'email'         => $email,
                    'role'          => $s['role'] ?? 'signer',
                    'signing_order' => $order,
                    'status'        => 'pending',
                ]);

                $signerRow = $sigSignerModel->find($signerId);
                $createdSigners[$index] = $signerRow;
                // Map temporary client key/index if provided
                if (isset($s['temp_key'])) {
                    $createdSigners[$s['temp_key']] = $signerRow;
                }
            }

            // Save fields
            foreach ($fieldsData as $f) {
                // Determine mapped signer ID
                $signerId = 0;
                if (isset($f['signer_index']) && isset($createdSigners[$f['signer_index']])) {
                    $signerId = (int)$createdSigners[$f['signer_index']]['id'];
                } elseif (isset($f['signer_key']) && isset($createdSigners[$f['signer_key']])) {
                    $signerId = (int)$createdSigners[$f['signer_key']]['id'];
                } elseif (isset($f['signer_id']) && $f['signer_id'] > 0) {
                    $signerId = (int)$f['signer_id'];
                } else {
                    // Default to first signer
                    $first = reset($createdSigners);
                    $signerId = $first ? (int)$first['id'] : 0;
                }

                if ($signerId <= 0) {
                    continue;
                }

                $sigFieldModel->create([
                    'request_id'  => $requestId,
                    'signer_id'   => $signerId,
                    'type'        => $f['type'] ?? 'signature',
                    'page'        => max(1, (int)($f['page'] ?? 1)),
                    'position_x'  => (float)($f['position_x'] ?? 10.0),
                    'position_y'  => (float)($f['position_y'] ?? 80.0),
                    'width'       => (float)($f['width'] ?? 24.0),
                    'height'      => (float)($f['height'] ?? 8.0),
                    'custom_text' => !empty($f['custom_text']) ? (string)$f['custom_text'] : null,
                    'required'    => isset($f['required']) ? (int)(bool)$f['required'] : 1,
                ]);
            }

            // Audit log
            $sigAuditModel->log(
                $requestId,
                'request_created',
                "Signature request '{$data['title']}' created by staff user #{$staffUserId}.",
                null,
                $staffUserId,
                ['signers_count' => count($signersData), 'fields_count' => count($fieldsData)]
            );

            AuditService::log('create_signature_request', 'signature_requests', $requestId, $staffUserId);

            $db->commit();

            // If send_now is requested, trigger invitations
            if (!empty($data['send_now'])) {
                self::sendInvitations($requestId);
            }

            return $requestId;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Dispatch invitation emails and notifications for a signature request.
     */
    public static function sendInvitations(int $requestId): void
    {
        $sigReqModel = new SignatureRequest();
        $sigSignerModel = new SignatureSigner();
        $sigAuditModel = new SignatureAuditEvent();

        $request = $sigReqModel->find($requestId);
        if (!$request) {
            return;
        }

        $sigReqModel->updateStatus($requestId, 'pending');

        $signers = $sigSignerModel->getByRequestId($requestId);
        $isSequential = ($request['signing_order'] === 'sequential');

        // If sequential, only send to signer(s) in turn (lowest signing order)
        $minOrder = PHP_INT_MAX;
        foreach ($signers as $s) {
            if ($s['status'] !== 'signed' && $s['role'] !== 'cc') {
                $minOrder = min($minOrder, (int)$s['signing_order']);
            }
        }

        foreach ($signers as $signer) {
            if ($signer['status'] === 'signed') {
                continue;
            }

            if ($isSequential && (int)$signer['signing_order'] > $minOrder) {
                // Not their turn yet
                continue;
            }

            self::sendSingleSignerInvite($request, $signer);
        }

        $sigAuditModel->log(
            $requestId,
            'request_sent',
            "Signature request '{$request['title']}' sent to signers.",
            null,
            (int)$request['created_by_user_id']
        );
    }

    /**
     * Send email and in-app notification to a single signer.
     */
    public static function sendSingleSignerInvite(array $request, array $signer): void
    {
        $token = $signer['token'];
        $title = $request['title'];
        $signerName = $signer['name'];
        $email = $signer['email'];

        $baseUrl = rtrim(\Application\Config\App::get('url'), '/');
        $signUrl = "{$baseUrl}/sign/{$token}";

        // Send transactional email via NotificationService
        $subject = "Signature Requested: {$title}";
        $html = "
            <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; color: #1e293b;'>
                <div style='background: #0f172a; padding: 20px; border-radius: 8px 8px 0 0; text-align: center;'>
                    <h2 style='color: #ffffff; margin: 0;'>TriNova Accounting</h2>
                </div>
                <div style='padding: 24px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 8px 8px;'>
                    <h3 style='color: #0f172a; margin-top: 0;'>Signature Requested</h3>
                    <p>Hello {$signerName},</p>
                    <p>You have been invited by TriNova Accounting to review and sign <strong>{$title}</strong>.</p>
                    <div style='margin: 28px 0; text-align: center;'>
                        <a href='{$signUrl}' style='background: #2563eb; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; font-size: 16px;'>Review & Sign Document</a>
                    </div>
                    <p style='color: #64748b; font-size: 13px;'>Or copy and paste this link in your browser: <br><a href='{$signUrl}' style='color: #2563eb;'>{$signUrl}</a></p>
                    <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;'>
                    <p style='color: #94a3b8; font-size: 12px; margin-bottom: 0;'>Secure digital signature portal powered by TriNova Accounting.</p>
                </div>
            </div>
        ";

        NotificationService::sendResendEmail($email, $subject, $html);

        // In-app notification if registered client user
        if (!empty($signer['user_id'])) {
            try {
                (new Notification())->create(
                    (int)$signer['user_id'],
                    'signature_request',
                    'signature_request:' . $request['id'],
                    'Signature Requested',
                    "Please review and digitally sign '{$title}'",
                    $signUrl
                );
            } catch (Throwable $e) {
                // Notifications don't fail invite flow
            }
        }
    }

    /**
     * Retrieve and validate an active signing session for a token.
     */
    public static function getSigningSession(string $token, ?string $ip = null, ?string $userAgent = null): array
    {
        $sigSignerModel = new SignatureSigner();
        $sigReqModel = new SignatureRequest();
        $sigFieldModel = new SignatureField();
        $sigAuditModel = new SignatureAuditEvent();

        $signer = $sigSignerModel->findByToken($token);
        if (!$signer) {
            throw new Exception("Invalid or non-existent signing link.", 404);
        }

        $requestId = (int)$signer['request_id'];
        $request = $sigReqModel->find($requestId);

        if (!$request) {
            throw new Exception("The associated document request is no longer available.", 404);
        }

        // Check if request is cancelled
        if ($request['status'] === 'cancelled' || !empty($request['deleted_at'])) {
            throw new Exception("This signature request has been cancelled by the practice.", 410);
        }

        // Check expiry
        if (!empty($request['expires_at']) && strtotime($request['expires_at']) < time()) {
            $sigReqModel->updateStatus($requestId, 'expired');
            throw new Exception("This signature request expired on {$request['expires_at']}.", 410);
        }

        // Record opened event if first time
        if ($signer['read_status'] === 'not_opened') {
            $sigSignerModel->markOpened((int)$signer['id'], $ip, $userAgent);
            $sigAuditModel->log(
                $requestId,
                'document_opened',
                "Signer {$signer['name']} ({$signer['email']}) opened the document for review.",
                (int)$signer['id'],
                null,
                ['ip' => $ip, 'user_agent' => $userAgent]
            );
        }

        // Check sequential turn
        $isTurn = $sigSignerModel->isTurnToSign((int)$signer['id']);

        // Load fields for this signer and for the whole document (for viewing placeholders)
        $myFields = $sigFieldModel->getBySignerId((int)$signer['id']);
        $allFields = $sigFieldModel->getByRequestId($requestId);

        return [
            'request'    => $request,
            'signer'     => $signer,
            'my_fields'  => $myFields,
            'all_fields' => $allFields,
            'is_turn'    => $isTurn,
        ];
    }

    /**
     * Submit and process completed fields for a signer.
     */
    public static function submitSignerFields(string $token, array $fieldSubmissions, ?string $ip = null, ?string $userAgent = null): array
    {
        $session = self::getSigningSession($token, $ip, $userAgent);
        $signer = $session['signer'];
        $request = $session['request'];
        $signerId = (int)$signer['id'];
        $requestId = (int)$request['id'];

        if ($signer['status'] === 'signed') {
            return [
                'success' => true,
                'already_signed' => true,
                'message' => 'You have already signed this document.',
                'request_id' => $requestId,
            ];
        }

        if (!$session['is_turn']) {
            throw new Exception("It is not your turn to sign this document yet. Please wait for previous signers to complete.", 403);
        }

        $sigFieldModel = new SignatureField();
        $sigSignerModel = new SignatureSigner();
        $sigAuditModel = new SignatureAuditEvent();

        $myFields = $sigFieldModel->getBySignerId($signerId);
        $myFieldsById = [];
        foreach ($myFields as $f) {
            $myFieldsById[(int)$f['id']] = $f;
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // Process each submitted field
            foreach ($fieldSubmissions as $k => $data) {
                $fieldId = (int)(is_array($data) && isset($data['field_id']) ? $data['field_id'] : $k);
                if (!isset($myFieldsById[$fieldId])) {
                    continue; // Ignore fields that do not belong to this signer
                }

                $field = $myFieldsById[$fieldId];
                $type = $field['type'];

                $sigData = null;
                $sigType = null;
                $customText = null;

                if ($type === 'signature' || $type === 'initials') {
                    $sigData = is_array($data) ? (string)($data['value'] ?? '') : (string)$data;
                    $rawType = is_array($data) ? (string)($data['sig_type'] ?? ($data['type'] ?? 'drawn')) : 'drawn';
                    $sigType = in_array($rawType, ['drawn', 'typed', 'uploaded'], true) ? $rawType : 'drawn';
                    if (empty($sigData)) {
                        continue;
                    }
                } elseif ($type === 'date') {
                    $val = is_array($data) ? ($data['value'] ?? '') : $data;
                    $customText = !empty($val) ? trim((string)$val) : date('d/m/Y');
                    $sigType = 'drawn';
                } elseif ($type === 'checkbox') {
                    $val = is_array($data) ? ($data['value'] ?? '') : $data;
                    $customText = !empty($val) ? '1' : '0';
                    $sigType = 'drawn';
                } else {
                    $val = is_array($data) ? ($data['value'] ?? '') : $data;
                    $customText = trim((string)$val);
                    $sigType = 'drawn';
                }

                $sigFieldModel->saveFieldValue($fieldId, $sigData, $sigType, $customText);
            }

            // Verify all required fields for this signer have been completed
            if ($sigFieldModel->hasUnsignedRequiredFields($signerId)) {
                throw new Exception("Please complete all required fields before finishing.");
            }

            // Mark signer as signed
            $sigSignerModel->markSigned($signerId, $ip, $userAgent);

            $sigAuditModel->log(
                $requestId,
                'signature_completed',
                "Signer {$signer['name']} ({$signer['email']}) completed and submitted their signature.",
                $signerId,
                null,
                ['ip' => $ip, 'user_agent' => $userAgent]
            );

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        // If sequential, check if next signer should now be notified
        if ($request['signing_order'] === 'sequential') {
            self::sendInvitations($requestId);
        }

        // Check if all signers have finished
        $allComplete = $sigSignerModel->haveAllSigned($requestId);
        $sealedResult = null;

        if ($allComplete) {
            $sealedResult = SignatureSealerService::seal($requestId);
        }

        return [
            'success'       => true,
            'signer_id'     => $signerId,
            'request_id'    => $requestId,
            'all_completed' => $allComplete,
            'sealed_result' => $sealedResult,
        ];
    }

    /**
     * Decline a signature request.
     */
    public static function declineRequest(string $token, string $reason, ?string $ip = null, ?string $userAgent = null): array
    {
        $session = self::getSigningSession($token, $ip, $userAgent);
        $signer = $session['signer'];
        $request = $session['request'];
        $signerId = (int)$signer['id'];
        $requestId = (int)$request['id'];

        $sigSignerModel = new SignatureSigner();
        $sigReqModel = new SignatureRequest();
        $sigAuditModel = new SignatureAuditEvent();

        $sigSignerModel->markDeclined($signerId, $reason, $ip, $userAgent);
        $sigReqModel->markDeclined($requestId, "Declined by {$signer['name']}: {$reason}");

        $sigAuditModel->log(
            $requestId,
            'document_declined',
            "Signer {$signer['name']} ({$signer['email']}) declined the document: {$reason}",
            $signerId,
            null,
            ['reason' => $reason, 'ip' => $ip, 'user_agent' => $userAgent]
        );

        // Notify staff creator
        try {
            (new Notification())->create(
                (int)$request['created_by_user_id'],
                'signature_declined',
                'signature_request:' . $requestId,
                'Signature Declined',
                "{$signer['name']} declined '{$request['title']}': {$reason}",
                '/staff/signatures/' . $requestId
            );
        } catch (Throwable $e) {
            // Notifications don't fail decline flow
        }

        return [
            'success'    => true,
            'request_id' => $requestId,
            'reason'     => $reason,
        ];
    }

    /**
     * Cancel a signature request (staff action).
     */
    public static function cancelRequest(int $requestId, int $staffUserId): bool
    {
        $sigReqModel = new SignatureRequest();
        $sigAuditModel = new SignatureAuditEvent();

        $request = $sigReqModel->find($requestId);
        if (!$request) {
            return false;
        }

        $res = $sigReqModel->softDelete($requestId);
        if ($res) {
            $sigAuditModel->log(
                $requestId,
                'request_cancelled',
                "Signature request cancelled by staff user #{$staffUserId}.",
                null,
                $staffUserId
            );
            AuditService::log('cancel_signature_request', 'signature_requests', $requestId, $staffUserId);
        }

        return $res;
    }
}
