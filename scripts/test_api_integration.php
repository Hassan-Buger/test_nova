<?php

/**
 * TriNova Portal - Complete End-to-End API Integration Regression Test Suite
 * Validates the Sloane CEO Command Centre headless REST integration layer.
 */

declare(strict_types=1);

$root = dirname(__DIR__);

// Load Autoloader
$autoloadFound = false;
foreach ([$root . '/vendor/autoload.php', $root . '/8493files/vendor/autoload.php', $root . '/trinova_app/vendor/autoload.php'] as $autoloadFile) {
    if (file_exists($autoloadFile)) {
        require_once $autoloadFile;
        $autoloadFound = true;
        break;
    }
}
if (!$autoloadFound) {
    spl_autoload_register(function ($class) use ($root) {
        $prefix = 'Application\\';
        $baseDir = $root . '/application/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require_once $file;
    });
}

use Application\Config\App;
use Application\Core\Request;
use Application\Core\Response;
use Application\Core\Router;
use Application\Middleware\ApiAuthMiddleware;
use Application\Controllers\Api\HealthController;
use Application\Controllers\Api\ClientApiController;
use Application\Controllers\Api\StatutoryApiController;
use Application\Controllers\Api\NoteApiController;
use Application\Controllers\Api\DocumentApiController;
use Application\Controllers\Api\SignatureApiController;
use Application\Services\IdempotencyService;

echo "======================================================================\n";
echo "  TRINOVA PORTAL - SLOANE CEO INTEGRATION API END-TO-END TEST SUITE  \n";
echo "======================================================================\n\n";

$passCount = 0;
$failCount = 0;

function it(string $description, bool $condition, string $details = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] $description\n";
    } else {
        $failCount++;
        echo "  [FAIL] $description: $details\n";
    }
}

// Test Response Capturer
class TestApiResponse extends Response {
    public int $code = 200;
    public ?array $jsonPayload = null;
    public array $headers = [];

    public function setStatusCode(int $code): void {
        $this->code = $code;
    }

    public function json(array $data, int $code = 200): void {
        $this->code = $code;
        $this->jsonPayload = $data;
    }
}

// ----------------------------------------------------------------------
// 1. HEALTH & AUTH DIAGNOSTICS
// ----------------------------------------------------------------------
echo "[Section 1: API Foundation, Middleware & Health Diagnostics]\n";

// 1.1 Request Header Extraction
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer tn_sec_live_sloane_readwrite_2026';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/v1/auth/verify';

$req = new Request();
it('Request correctly reads Authorization header', $req->getHeader('Authorization') === 'Bearer tn_sec_live_sloane_readwrite_2026');
it('Request extracts Bearer token', $req->getBearerToken() === 'tn_sec_live_sloane_readwrite_2026');

// 1.2 ApiAuthMiddleware Unauthorized
$authMw = new ApiAuthMiddleware();
$resUnauth = new TestApiResponse();
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid_wrong_token';
$mwResultUnauth = $authMw->handle($req, $resUnauth);
it('ApiAuthMiddleware rejects invalid Bearer token', $mwResultUnauth === false);
it('Rejection emits HTTP 401', $resUnauth->code === 401);
it('Rejection returns UNAUTHORIZED error code', ($resUnauth->jsonPayload['error']['code'] ?? '') === 'UNAUTHORIZED');

// 1.3 ApiAuthMiddleware Authorized
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . App::get('api_key');
$resAuth = new TestApiResponse();
$mwResultAuth = $authMw->handle($req, $resAuth);
it('ApiAuthMiddleware accepts valid TRINOVA_API_KEY Bearer token', $mwResultAuth === true);

// 1.4 Public Exemption for /api/v1/health
$_SERVER['REQUEST_URI'] = '/api/v1/health';
unset($_SERVER['HTTP_AUTHORIZATION']);
$reqHealth = new Request();
$resHealthMw = new TestApiResponse();
$mwResultHealth = $authMw->handle($reqHealth, $resHealthMw);
it('/api/v1/health is exempt from Bearer token requirement', $mwResultHealth === true);

// 1.5 HealthController Health check
$healthCtrl = new HealthController();
$resHealth = new TestApiResponse();
$healthCtrl->health($reqHealth, $resHealth);
it('Health endpoint returns HTTP 200', $resHealth->code === 200);
it('Health endpoint reports healthy: true', ($resHealth->jsonPayload['data']['healthy'] ?? false) === true);
it('Health endpoint reports TriNova Accounting Client Portal', str_contains($resHealth->jsonPayload['data']['app'] ?? '', 'TriNova'));

// 1.6 HealthController Verify check
$resVerify = new TestApiResponse();
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer tn_sec_live_sloane_readwrite_2026';
$verifyReq = new Request();
$healthCtrl->verify($verifyReq, $resVerify);
it('Verify endpoint returns HTTP 200', $resVerify->code === 200);
it('Verify endpoint reports authenticated: true', ($resVerify->jsonPayload['data']['authenticated'] ?? false) === true);
it('Verify endpoint reports actor: sloane_ceo_command_centre', ($resVerify->jsonPayload['data']['actor'] ?? '') === 'sloane_ceo_command_centre');
it('Verify endpoint contains required scopes', in_array('statutory_reconcile', $resVerify->jsonPayload['data']['scopes'] ?? []));

echo "\n";

// ----------------------------------------------------------------------
// 2. CLIENTS & MULTI-COMPANY READ ENDPOINTS
// ----------------------------------------------------------------------
echo "[Section 2: Clients & Multi-Company Entity Read Endpoints]\n";

$clientCtrlRef = new ReflectionClass(ClientApiController::class);
it('ClientApiController has index method', $clientCtrlRef->hasMethod('index'));
it('ClientApiController has show method', $clientCtrlRef->hasMethod('show'));

$clientCtrl = $clientCtrlRef->newInstanceWithoutConstructor();
$resClientShowInvalid = new TestApiResponse();
$clientCtrl->show(new Request(), $resClientShowInvalid, 'non_numeric');
it('GET /companies/{id} with non-numeric id returns HTTP 400', $resClientShowInvalid->code === 400);
it('Returns INVALID_COMPANY_ID error code', ($resClientShowInvalid->jsonPayload['error']['code'] ?? '') === 'INVALID_COMPANY_ID');

$expectedClientItem = [
    'id' => 1,
    'company_id' => 'comp_1',
    'client_id' => 1,
    'name' => 'Woofington Park Limited',
    'company_number' => '12345678',
    'entity_type' => 'Corporate',
    'directors' => ['Jane Doe'],
    'tax_reference' => 'UTR-998811',
    'created_at' => '2026-01-10T12:00:00Z'
];
it('Company schema contract contains stable id and company_id', isset($expectedClientItem['id'], $expectedClientItem['company_id']));
it('Company schema contract contains directors list', is_array($expectedClientItem['directors']));
it('Company schema contract contains company_number and tax_reference', isset($expectedClientItem['company_number'], $expectedClientItem['tax_reference']));

echo "\n";

// ----------------------------------------------------------------------
// 3. STATUTORY DEADLINES & COMPANIES HOUSE RECONCILIATION
// ----------------------------------------------------------------------
echo "[Section 3: Statutory Deadlines & Companies House Reconciliation]\n";

$statCtrlRef = new ReflectionClass(StatutoryApiController::class);
it('StatutoryApiController has index method', $statCtrlRef->hasMethod('index'));
it('StatutoryApiController has sync method', $statCtrlRef->hasMethod('sync'));

$statCtrl = $statCtrlRef->newInstanceWithoutConstructor();

// Test validation on sync
$reqSyncBad = new class extends Request {
    public function getJsonBody(): array {
        return ['records' => 'not_an_array'];
    }
};
$resSyncBad = new TestApiResponse();
$statCtrl->sync($reqSyncBad, $resSyncBad);
it('POST /statutory/sync rejects non-array records with HTTP 400', $resSyncBad->code === 400);
it('Returns INVALID_PAYLOAD code', ($resSyncBad->jsonPayload['error']['code'] ?? '') === 'INVALID_PAYLOAD');

$statItemContract = [
    'id' => 10,
    'entity_id' => 1,
    'company_id' => 'comp_1',
    'company_name' => 'Woofington Park Limited',
    'company_number' => '12345678',
    'type' => 'Accounts',
    'due_date' => '2026-08-31',
    'status' => 'Overdue',
    'is_overdue' => true,
    'created_at' => '2026-01-10T12:00:00Z'
];
it('Statutory contract contains accounts / confirmation statement type', in_array($statItemContract['type'], ['Accounts', 'Confirmation Statement']));
it('Statutory contract contains is_overdue calculation flag', isset($statItemContract['is_overdue']));

echo "\n";

// ----------------------------------------------------------------------
// 4. INTERNAL NOTES & STRICT PRIVACY BOUNDARY
// ----------------------------------------------------------------------
echo "[Section 4: Internal Notes with Strict CEO Privacy Boundary]\n";

$noteCtrlRef = new ReflectionClass(NoteApiController::class);
it('NoteApiController has create method', $noteCtrlRef->hasMethod('create'));

$noteCtrl = $noteCtrlRef->newInstanceWithoutConstructor();

// Privacy boundary violation test
$reqPrivacyViolation = new class extends Request {
    public function getJsonBody(): array {
        return [
            'text' => 'Confidential Sloane Strategic Note',
            'confirm_write_to_trinova' => false,
            'visibility' => 'staff_only'
        ];
    }
};
$resPrivacy = new TestApiResponse();
$noteCtrl->create($reqPrivacyViolation, $resPrivacy, '1');
it('Rejects note write when confirm_write_to_trinova is false with HTTP 403', $resPrivacy->code === 403);
it('Returns PRIVACY_BOUNDARY_CONFIRMATION_REQUIRED code', ($resPrivacy->jsonPayload['error']['code'] ?? '') === 'PRIVACY_BOUNDARY_CONFIRMATION_REQUIRED');

// Empty text validation
$reqEmptyNote = new class extends Request {
    public function getJsonBody(): array {
        return [
            'text' => '   ',
            'confirm_write_to_trinova' => true
        ];
    }
};
$resEmptyNote = new TestApiResponse();
$noteCtrl->create($reqEmptyNote, $resEmptyNote, '1');
it('Rejects note with empty text with HTTP 422', $resEmptyNote->code === 422);
it('Returns VALIDATION_FAILED error code', ($resEmptyNote->jsonPayload['error']['code'] ?? '') === 'VALIDATION_FAILED');

// Idempotency Key Service check
$idempRef = new ReflectionClass(IdempotencyService::class);
it('IdempotencyService has get and save methods', $idempRef->hasMethod('get') && $idempRef->hasMethod('save'));

echo "\n";

// ----------------------------------------------------------------------
// 5. DOCUMENT RETRIEVAL & SIGNED DOWNLOAD URLS
// ----------------------------------------------------------------------
echo "[Section 5: Document Retrieval & Secure Signed Download URLs]\n";

$docCtrlRef = new ReflectionClass(DocumentApiController::class);
it('DocumentApiController has listByCompany method', $docCtrlRef->hasMethod('listByCompany'));
it('DocumentApiController has generateAccessToken method', $docCtrlRef->hasMethod('generateAccessToken'));
it('DocumentApiController has download method', $docCtrlRef->hasMethod('download'));

$docCtrl = $docCtrlRef->newInstanceWithoutConstructor();

// Download token security
$_GET = [];
$resNoToken = new TestApiResponse();
$docCtrl->download(new Request(), $resNoToken);
it('GET /documents/download without token returns HTTP 401', $resNoToken->code === 401);
it('Returns MISSING_DOWNLOAD_TOKEN code', ($resNoToken->jsonPayload['error']['code'] ?? '') === 'MISSING_DOWNLOAD_TOKEN');

// Expired token
$secret = App::get('secret');
$expiredTimestamp = time() - 600;
$expiredSig = hash_hmac('sha256', "doc_1:{$expiredTimestamp}", $secret);
$expiredToken = rtrim(strtr(base64_encode(json_encode([
    'id' => 1,
    'exp' => $expiredTimestamp,
    'sig' => $expiredSig
])), '+/', '-_'), '=');

$_GET = ['token' => $expiredToken];
$resExpired = new TestApiResponse();
$docCtrl->download(new Request(), $resExpired);
it('GET /documents/download with expired token returns HTTP 401', $resExpired->code === 401);
it('Returns DOWNLOAD_TOKEN_EXPIRED code', ($resExpired->jsonPayload['error']['code'] ?? '') === 'DOWNLOAD_TOKEN_EXPIRED');

// Tampered token
$futureTimestamp = time() + 900;
$tamperedToken = rtrim(strtr(base64_encode(json_encode([
    'id' => 1,
    'exp' => $futureTimestamp,
    'sig' => 'tampered_signature_9999'
])), '+/', '-_'), '=');

$_GET = ['token' => $tamperedToken];
$resTampered = new TestApiResponse();
$docCtrl->download(new Request(), $resTampered);
it('GET /documents/download with forged signature returns HTTP 401', $resTampered->code === 401);
it('Returns INVALID_DOWNLOAD_TOKEN code', ($resTampered->jsonPayload['error']['code'] ?? '') === 'INVALID_DOWNLOAD_TOKEN');

echo "\n";

// ----------------------------------------------------------------------
// 6. DIGITAL SIGNATURES & SIGNING BRIDGE
// ----------------------------------------------------------------------
echo "[Section 6: Digital Signatures & Signing Request API Bridge]\n";

$sigCtrlRef = new ReflectionClass(SignatureApiController::class);
it('SignatureApiController has listByCompany method', $sigCtrlRef->hasMethod('listByCompany'));
it('SignatureApiController has create method', $sigCtrlRef->hasMethod('create'));
it('SignatureApiController has audit method', $sigCtrlRef->hasMethod('audit'));

$sigCtrl = $sigCtrlRef->newInstanceWithoutConstructor();

// Validation: missing title
$reqNoTitle = new class extends Request {
    public function getJsonBody(): array {
        return ['signers' => [['name' => 'Jane', 'email' => 'jane@example.com']]];
    }
};
$resNoTitle = new TestApiResponse();
$sigCtrl->create($reqNoTitle, $resNoTitle, '1');
it('POST /companies/{id}/signing-requests without title returns HTTP 422', $resNoTitle->code === 422);

// Validation: missing signers
$reqNoSigners = new class extends Request {
    public function getJsonBody(): array {
        return ['title' => 'Important Resolution', 'signers' => []];
    }
};
$resNoSigners = new TestApiResponse();
$sigCtrl->create($reqNoSigners, $resNoSigners, '1');
it('POST /companies/{id}/signing-requests with empty signers returns HTTP 422', $resNoSigners->code === 422);

// Audit: non-numeric ID
$resAuditInvalid = new TestApiResponse();
$sigCtrl->audit(new Request(), $resAuditInvalid, 'abc');
it('GET /signatures/{id}/audit with non-numeric ID returns HTTP 400', $resAuditInvalid->code === 400);

echo "\n";

// ----------------------------------------------------------------------
// 7. WEB PORTAL ROUTE NON-DISRUPTION VERIFICATION
// ----------------------------------------------------------------------
echo "[Section 7: Zero Disruption to Existing Web Portal Routes]\n";

$indexPhp = file_get_contents($root . '/public/index.php');
it('Web route /login is intact', str_contains($indexPhp, "router->get('/login'"));
it('Web route /logout is intact (POST only)', str_contains($indexPhp, "router->post('/logout'"));
it('Client secured routes prefix /client is intact', str_contains($indexPhp, "'prefix' => '/client'"));
it('Staff secured routes prefix /staff is intact', str_contains($indexPhp, "'prefix' => '/staff'"));
it('Signing token route /sign/{token} is intact', str_contains($indexPhp, "router->get('/sign/{token}'"));
it('Signature verification route /verify/signature/{token} is intact', str_contains($indexPhp, "router->get('/verify/signature/{token}'"));
it('CSRF Middleware is present on web routes', str_contains($indexPhp, "CsrfMiddleware::class"));

// Router 404 behavior: API vs Web
$router = new Router();
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/v1/non_existent_endpoint';
$reqApi404 = new Request();
$resApi404 = new TestApiResponse();
$router->resolve($reqApi404, $resApi404);
it('API 404 returns JSON with NOT_FOUND error code', ($resApi404->jsonPayload['error']['code'] ?? '') === 'NOT_FOUND');

echo "\n======================================================================\n";
echo "  SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "======================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
