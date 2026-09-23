<?php

namespace Application\Controllers\Staff;

use Application\Core\Controller;
use Application\Core\Request;
use Application\Core\Response;
use Application\Models\Client;
use Application\Models\ClientEntity;
use Application\Models\Deadline;
use Application\Models\DocumentRequest;
use Application\Models\EntityAccess;
use Application\Core\Session;

class ClientController extends Controller
{
    private Client $clientModel;
    private ClientEntity $entityModel;
    private DocumentRequest $requestModel;
    private Deadline $deadlineModel;

    public function __construct()
    {
        $this->clientModel = new Client();
        $this->entityModel = new ClientEntity();
        $this->requestModel = new DocumentRequest();
        $this->deadlineModel = new Deadline();
    }

    public function index(Request $request, Response $response): void
    {
        $query = $request->getQueryParams();
        $search = trim((string) ($query['q'] ?? ''));
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = (int) ($query['per_page'] ?? 10);
        if (!in_array($perPage, [10, 20, 50], true)) {
            $perPage = 10;
        }
        $pagination = $this->clientModel->paginate($search, $page, $perPage);

        $this->render('staff/clients/index', [
            'pageTitle' => 'Clients Overview',
            'clients'   => $pagination['items'],
            'search' => $search,
            'pagination' => $pagination,
        ], 'main');
    }

    public function exportCsv(Request $request, Response $response): void
    {
        $query=$request->getQueryParams();
        if(isset($query['q']) && !is_string($query['q'])){
            Session::setFlash('error','The selected export filter is invalid.');
            $response->redirect('/staff/clients');
            return;
        }
        $search=trim((string)($query['q']??''));
        if(strlen($search)>100 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$search)){
            Session::setFlash('error','The selected export filter is invalid.');
            $response->redirect('/staff/clients');
            return;
        }

        try {
            \Application\Services\SchemaGuard::assertClientCsvReady();
            if($this->clientModel->countForExport($search)===0){
                Session::setFlash('error','No clients are available for the selected CSV export.');
                $response->redirect('/staff/clients'.($search!==''?'?q='.urlencode($search):''));
                return;
            }
            \Application\Services\AuditService::log('client_csv_export','clients',null);
            while(ob_get_level()>0) ob_end_clean();
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="trinova-clients-'.date('Y-m-d').'.csv"');
            header('Cache-Control: private, no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
            (new \Application\Services\ClientCsvExportService())->stream($this->clientModel,$search);
            exit;
        } catch(\Application\Exceptions\SystemSetupException $e){
            \Application\Services\ErrorHandler::report($e,$request);
            if(!headers_sent()){
                if($request->isAjax()){$response->json(['success'=>false,'message'=>\Application\Services\ErrorHandler::SETUP_MESSAGE],503);return;}
                Session::setFlash('error',\Application\Services\ErrorHandler::SETUP_MESSAGE);$response->redirect('/staff/clients');
            }
            exit;
        } catch(\Throwable $e){
            \Application\Services\ErrorHandler::report($e,$request);
            if(!headers_sent()){
                if($request->isAjax()){$response->json(['success'=>false,'message'=>\Application\Services\ErrorHandler::CLIENT_DATA_MESSAGE],500);return;}
                Session::setFlash('error',\Application\Services\ErrorHandler::CLIENT_DATA_MESSAGE);
                $response->redirect('/staff/clients');
            }
            exit;
        }
    }

    public function show(Request $request, Response $response, int $id): void
    {
        $client = $this->clientModel->findById($id);
        if (!$client) {
            $response->setStatusCode(404);
            die('Client record not found.');
        }

        $entities = $this->entityModel->getByClientId($id);
        $outstanding = $this->requestModel->getOutstandingByClientId($id);
        $deadlineGroups = $this->deadlineModel->getGroupedByClient($id);
        $groupedByEntity = [];
        foreach ($deadlineGroups as $group) $groupedByEntity[(int)$group['entity_id']] = $group;
        $deadlineGroups = array_map(static function(array $entity) use ($groupedByEntity): array {
            return $groupedByEntity[(int)$entity['id']] ?? ['entity_id'=>(int)$entity['id'], 'entity_name'=>$entity['company_name'], 'entity_type'=>$entity['entity_type'], 'deadlines'=>[]];
        }, $entities);

        $auditModel = new \Application\Models\AuditLog();
        $auditLogs = $auditModel->getByUserId((int)$client['user_id'], 20);

        $documentModel = new \Application\Models\Document();
        $documents = $documentModel->getByClientId($id);

        $meetingModel = new \Application\Models\Meeting();
        $meetings = $meetingModel->getByClientId($id);
        $accessModel = new EntityAccess();
        $directorsByEntity=[];$contactsByEntity=[];
        foreach($entities as $entity){$entityId=(int)$entity['id'];$directorsByEntity[$entityId]=$accessModel->directors($entityId);$contactsByEntity[$entityId]=$accessModel->contacts($entityId);}

        $this->render('staff/clients/show', [
            'pageTitle'   => 'Client profile',
            'client'      => $client,
            'entities'    => $entities,
            'outstanding' => $outstanding,
            'deadlineGroups' => $deadlineGroups,
            'auditLogs'   => $auditLogs,
            'documents'   => $documents,
            'meetings'    => $meetings,
            'directorsByEntity' => $directorsByEntity,
            'contactsByEntity' => $contactsByEntity,
            'eligibleDirectors' => $accessModel->eligibleDirectors(),
        ], 'main');
    }

    public function create(Request $request, Response $response): void
    {
        $body    = $request->getBody();
        $name    = trim($body['name'] ?? '');
        $email   = trim($body['email'] ?? '');
        $phone   = trim($body['phone'] ?? '');
        $address = trim($body['address'] ?? '');
        $aml     = trim($body['aml_status'] ?? 'Action Required');

        if (empty($name) || empty($email)) {
            if ($request->isAjax()) {
                $response->json(['success' => false, 'message' => 'Client name and email are required.'], 422);
                return;
            }
            \Application\Core\Session::setFlash('error', 'Client name and email are required.');
            $response->redirect('/staff/clients');
            return;
        }

        $userModel = new \Application\Models\User();
        if ($userModel->findByEmail($email)) {
            if ($request->isAjax()) {
                $response->json(['success' => false, 'message' => "User email '{$email}' already registered."], 409);
                return;
            }
            \Application\Core\Session::setFlash('error', "User email '{$email}' already registered.");
            $response->redirect('/staff/clients');
            return;
        }

        $customPass = trim((string)($body['password'] ?? ''));
        $passwordToHash = $customPass !== '' ? $customPass : 'password123';
        $userId = $userModel->create([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => password_hash($passwordToHash, PASSWORD_BCRYPT),
            'role'          => 'client',
            'status'        => $customPass !== '' ? 'active' : 'pending_activation',
        ]);

        $activationToken = bin2hex(random_bytes(32));
        $userModel->storeActivationToken($userId, $activationToken);

        $clientId = $this->clientModel->create([
            'user_id'    => $userId,
            'phone'      => $phone,
            'address'    => $address,
            'aml_status' => $aml,
        ]);

        $entityName = trim((string)($body['entity_name'] ?? ''));
        if ($entityName !== '') {
            $entityId = $this->entityModel->create([
                'client_id' => $clientId,
                'company_name' => $entityName,
                'entity_type' => trim((string)($body['entity_type'] ?? 'Other')) ?: 'Other',
                'entity_scope' => $this->entityScope($body),
                'company_number' => trim((string)($body['company_number'] ?? '')) ?: null,
                'tax_reference' => trim((string)($body['tax_reference'] ?? '')) ?: null,
                'attributes' => $this->entityAttributes($body),
            ]);
            if ($this->entityScope($body)==='company') (new EntityAccess())->linkDirector($entityId,$userId,(int)Session::get('user_id'));
            $this->createInlineDeadlines($clientId, $entityId, $body);
        }

        $appUrl = \Application\Config\App::get('url');
        $activationLink = rtrim($appUrl, '/') . '/activate?token=' . urlencode($activationToken);

        $emailSent = false;
        try {
            $otp = (new \Application\Models\OtpChallenge())->issue($userId, $email, \Application\Models\OtpChallenge::ACTIVATION);
            if ($otp['ok']) {
                $emailSent = \Application\Services\NotificationService::sendWelcomeActivationEmail($email, $name, $activationLink, $otp['code']);
            }
        } catch (\Throwable $e) {
            error_log('[TriNova ClientController] Welcome email note: ' . $e->getMessage());
        }

        \Application\Services\AuditService::log('client_created', 'clients', $clientId);

        $msg = "Client account for '{$name}' created successfully!";
        if ($emailSent) {
            $msg = "Client account for '{$name}' created! A welcome activation email has been dispatched.";
        }

        if ($request->isAjax()) {
            $response->json(['success' => true, 'message' => $msg, 'redirect' => '/staff/clients']);
            return;
        }

        \Application\Core\Session::setFlash('success', $msg);
        $response->redirect('/staff/clients');
    }

    public function resetPassword(Request $request, Response $response): void
    {
        $body        = $request->getBody();
        $clientId    = (int)($body['client_id'] ?? 0);
        $newPassword = trim($body['new_password'] ?? '');

        if ($clientId <= 0) {
            if ($request->isAjax()) {
                $response->json(['success' => false, 'message' => 'Client ID and new password are required.'], 400);
                return;
            }
            \Application\Core\Session::setFlash('error', 'Client ID and new password are required.');
            $response->redirect('/staff/clients');
            return;
        }

        $client = $this->clientModel->findById($clientId);
        if (!$client) {
            if ($request->isAjax()) {
                $response->json(['success' => false, 'message' => 'Client account not found.'], 404);
                return;
            }
            \Application\Core\Session::setFlash('error', 'Client not found.');
            $response->redirect('/staff/clients');
            return;
        }

        $otpModel = new \Application\Models\OtpChallenge();
        $otp = $otpModel->issue((int)$client['user_id'], $client['email'], \Application\Models\OtpChallenge::PASSWORD_RESET);
        $resetToken=bin2hex(random_bytes(32)); (new \Application\Models\User())->createResetToken($client['email'],$resetToken,date('Y-m-d H:i:s',time()+600));
        $resetLink=rtrim(\Application\Config\App::get('url'),'/').'/password/verify?token='.urlencode($resetToken);
        if (!$otp['ok'] || !\Application\Services\NotificationService::sendVerificationCodeEmail($client['email'], $client['name'], $otp['code'], 'password reset', $resetLink)) {
            $otpModel->invalidate((int)$client['user_id'], \Application\Models\OtpChallenge::PASSWORD_RESET);
            $msg = 'We could not send the password-reset verification code. Please try again.';
            if ($request->isAjax()) { $response->json(['success'=>false,'message'=>$msg],422); return; }
            \Application\Core\Session::setFlash('error',$msg); $response->redirect('/staff/clients/'.$clientId); return;
        }

        \Application\Services\AuditService::log('staff_reset_client_password', 'users', $client['user_id']);
        
        $msg = "A password-reset verification code was sent to '{$client['name']}'.";
        if ($request->isAjax()) {
            $response->json(['success' => true, 'message' => $msg]);
            return;
        }

        \Application\Core\Session::setFlash('success', $msg);
        $response->redirect('/staff/clients/' . $clientId);
    }

    public function delete(Request $request, Response $response): void
    {
        $body     = $request->getBody();
        $clientId = (int)($body['client_id'] ?? 0);
        $confirmation = trim((string)($body['confirm_delete'] ?? ''));

        if ($clientId <= 0 || $confirmation !== 'DELETE') {
            if ($request->isAjax()) {
                $response->json(['success' => false, 'message' => 'Type DELETE to confirm moving this client to Trash.'], 422);
                return;
            }
            \Application\Core\Session::setFlash('error', 'Type DELETE to confirm moving this client to Trash.');
            $response->redirect('/staff/clients');
            return;
        }

        $client = $this->clientModel->findById($clientId);
        if ($client) {
            $name = $client['name'];
            $this->clientModel->delete($clientId);
            \Application\Services\AuditService::log('client_deleted', 'clients', $clientId);

            $msg = "Client account '{$name}' moved to Trash successfully.";
            if ($request->isAjax()) {
                $response->json(['success' => true, 'message' => $msg, 'redirect' => '/staff/clients']);
                return;
            }
            \Application\Core\Session::setFlash('success', $msg);
        }

        $response->redirect('/staff/clients');
    }

    public function addEntity(Request $request, Response $response): void
    {
        $body        = $request->getBody();
        $clientId    = (int)($body['client_id'] ?? 0);
        $companyName = trim($body['entity_name'] ?? $body['company_name'] ?? '');
        $companyNum  = trim($body['company_number'] ?? '');
        $taxRef      = trim($body['tax_reference'] ?? '');

        if ($clientId > 0 && !empty($companyName)) {
            $entityId = $this->entityModel->create([
                'client_id'      => $clientId,
                'company_name'   => $companyName,
                'entity_type'    => trim((string)($body['entity_type'] ?? 'Other')) ?: 'Other',
                'entity_scope'   => $this->entityScope($body),
                'company_number' => $companyNum,
                'tax_reference'  => $taxRef,
                'attributes'     => $this->entityAttributes($body),
            ]);
            $client=$this->clientModel->findById($clientId);
            if ($this->entityScope($body)==='company' && $client) (new EntityAccess())->linkDirector($entityId,(int)$client['user_id'],(int)Session::get('user_id'));
            $this->createInlineDeadlines($clientId, $entityId, $body);

            \Application\Services\AuditService::log('entity_added', 'client_entities', $clientId);
            \Application\Core\Session::setFlash('success', "Business entity '{$companyName}' linked to client.");
        }

        $response->redirect('/staff/clients/' . $clientId);
    }

    private function entityAttributes(array $body): array
    {
        $map = [
            'vat_number' => 'VAT registration number',
            'ct_utr' => 'Corporation Tax UTR',
            'accounting_year_end' => 'Accounting year end',
            'personal_utr' => 'Personal UTR',
            'tax_year' => 'Tax year',
        ];
        $attributes = [];
        foreach ($map as $key => $label) {
            $value = trim((string)($body[$key] ?? ''));
            if ($value !== '') $attributes[$key] = ['label'=>$label, 'value'=>$value];
        }
        $customLabel = trim((string)($body['custom_attribute_label'] ?? ''));
        $customValue = trim((string)($body['custom_attribute_value'] ?? ''));
        if ($customLabel !== '' && $customValue !== '') {
            $attributes['custom_' . substr(hash('sha256', strtolower($customLabel)), 0, 12)] = ['label'=>$customLabel, 'value'=>$customValue];
        }
        return $attributes;
    }

    public function linkDirector(Request $request, Response $response): void
    {
        $body=$request->getBody();$entityId=(int)($body['entity_id']??0);$userId=(int)($body['user_id']??0);$returnClient=(int)($body['return_client_id']??0);
        if((new EntityAccess())->linkDirector($entityId,$userId,(int)Session::get('user_id'))){\Application\Services\AuditService::log('director_linked','client_entities',$entityId);Session::setFlash('success','Director access added.');}
        else Session::setFlash('error','Director could not be linked to this company record.');
        $response->redirect('/staff/clients/'.$returnClient);
    }

    public function unlinkDirector(Request $request, Response $response): void
    {
        $body=$request->getBody();$entityId=(int)($body['entity_id']??0);$userId=(int)($body['user_id']??0);$returnClient=(int)($body['return_client_id']??0);
        if((new EntityAccess())->unlinkDirector($entityId,$userId)){\Application\Services\AuditService::log('director_unlinked','client_entities',$entityId);Session::setFlash('success','Director access removed.');}
        $response->redirect('/staff/clients/'.$returnClient);
    }

    private function entityScope(array $body): string
    {
        $explicit=(string)($body['entity_scope']??'');
        if(in_array($explicit,['company','personal'],true)) return $explicit;
        return str_contains(strtolower((string)($body['entity_type']??'')),'personal') ? 'personal' : 'company';
    }

    private function createInlineDeadlines(int $clientId, int $entityId, array $body): void
    {
        $types = is_array($body['deadline_type'] ?? null) ? $body['deadline_type'] : [];
        $dates = is_array($body['deadline_due_date'] ?? null) ? $body['deadline_due_date'] : [];
        foreach ($types as $index => $type) {
            $type = trim((string)$type);
            $date = trim((string)($dates[$index] ?? ''));
            if ($type === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) continue;
            $entity=$this->entityModel->findById($entityId);
            $this->deadlineModel->create(['client_id'=>$clientId, 'entity_id'=>$entityId, 'scope'=>$entity['entity_scope']??'company', 'type'=>$type, 'due_date'=>$date, 'status'=>'Pending']);
        }
    }

    public function bulkDelete(Request $request, Response $response): void
    {
        $rawIds = $request->input('ids', []);
        $ids = is_array($rawIds) ? array_map('intval', array_filter($rawIds)) : [];

        if (empty($ids)) {
            if ($request->isAjax()) {
                $response->json(['success' => false, 'message' => 'No clients selected for deletion.'], 422);
                return;
            }
            Session::setFlash('error', 'No clients selected for deletion.');
            $response->redirect('/staff/clients');
            return;
        }

        $count = $this->clientModel->bulkSoftDelete($ids);
        if ($count > 0) {
            \Application\Services\AuditService::log('client_bulk_deleted', 'clients', null, null, ['count' => $count, 'ids' => $ids]);
            $msg = "{$count} client account(s) deleted successfully.";
            if ($request->isAjax()) {
                $response->json(['success' => true, 'message' => $msg, 'redirect' => '/staff/clients']);
                return;
            }
            Session::setFlash('success', $msg);
        }
        $response->redirect('/staff/clients');
    }

    public function deleteEntity(Request $request, Response $response): void
    {
        $body = $request->getBody();
        $entityId = (int)($body['entity_id'] ?? 0);
        $clientId = (int)($body['client_id'] ?? 0);

        if ($entityId > 0 && $this->entityModel->softDelete($entityId)) {
            \Application\Services\AuditService::log('entity_deleted', 'client_entities', $entityId);
            Session::setFlash('success', 'Business entity removed.');
        } else {
            Session::setFlash('error', 'Failed to remove business entity.');
        }

        $response->redirect('/staff/clients/' . $clientId);
    }
}
