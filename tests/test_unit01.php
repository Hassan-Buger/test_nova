<?php

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

echo "=== UNIT 01: API FOUNDATION, TOKEN MIDDLEWARE & HEALTH TEST ===\n\n";

function assertTest(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("FAILED: " . $message);
    }
    echo " [PASS] $message\n";
}

// 1. Test Request methods: getHeader, getBearerToken, getJsonBody
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer tn_sec_live_sloane_readwrite_2026';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/v1/auth/verify';

$req = new Request();
assertTest($req->getHeader('Authorization') === 'Bearer tn_sec_live_sloane_readwrite_2026', 'Request::getHeader extracts Authorization header');
assertTest($req->getHeader('authorization') === 'Bearer tn_sec_live_sloane_readwrite_2026', 'Request::getHeader is case-insensitive');
assertTest($req->getBearerToken() === 'tn_sec_live_sloane_readwrite_2026', 'Request::getBearerToken extracts token');

// 2. Test ApiAuthMiddleware unauthorized
$mw = new ApiAuthMiddleware();
$testResponse = new class extends Response {
    public int $code = 0;
    public ?array $jsonPayload = null;
    public function setStatusCode(int $code): void { $this->code = $code; }
    public function json(array $data, int $code = 200): void {
        $this->code = $code;
        $this->jsonPayload = $data;
    }
};

$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid_token';
$res1 = new $testResponse();
$result = $mw->handle($req, $res1);
assertTest($result === false, 'ApiAuthMiddleware rejects invalid Bearer token');
assertTest($res1->code === 401, 'Rejection emits HTTP 401');
assertTest($res1->jsonPayload['status'] === 'error', 'Rejection response contains status: error');
assertTest($res1->jsonPayload['error']['code'] === 'UNAUTHORIZED', 'Rejection error code is UNAUTHORIZED');

// 3. Test ApiAuthMiddleware authorized
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . App::get('api_key');
$res2 = new $testResponse();
$result = $mw->handle($req, $res2);
assertTest($result === true, 'ApiAuthMiddleware allows valid Bearer token');

// 4. Test ApiAuthMiddleware public route exemption
$_SERVER['REQUEST_URI'] = '/api/v1/health';
unset($_SERVER['HTTP_AUTHORIZATION']);
$reqHealth = new Request();
$res3 = new $testResponse();
$result = $mw->handle($reqHealth, $res3);
assertTest($result === true, 'ApiAuthMiddleware allows /api/v1/health without token');

// 5. Test HealthController endpoints
$controller = new HealthController();
$healthRes = new $testResponse();
$controller->health($reqHealth, $healthRes);
assertTest($healthRes->code === 200, 'HealthController::health returns status 200');
assertTest($healthRes->jsonPayload['status'] === 'success', 'HealthController::health status is success');
assertTest($healthRes->jsonPayload['data']['healthy'] === true, 'HealthController::health reports healthy: true');
assertTest(isset($healthRes->jsonPayload['data']['database']), 'HealthController::health reports database status');

$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer tn_sec_live_sloane_readwrite_2026';
$verifyReq = new Request();
$verifyRes = new $testResponse();
$controller->verify($verifyReq, $verifyRes);
assertTest($verifyRes->code === 200, 'HealthController::verify returns status 200');
assertTest($verifyRes->jsonPayload['data']['authenticated'] === true, 'HealthController::verify reports authenticated: true');
assertTest($verifyRes->jsonPayload['data']['actor'] === 'sloane_ceo_command_centre', 'HealthController::verify actor is sloane_ceo_command_centre');
assertTest(in_array('statutory_reconcile', $verifyRes->jsonPayload['data']['scopes']), 'HealthController::verify contains expected scopes');

// 6. Test Router methods & API 404
$router = new Router();
assertTest(method_exists($router, 'patch'), 'Router has patch() method');
assertTest(method_exists($router, 'delete'), 'Router has delete() method');
assertTest(method_exists($router, 'options'), 'Router has options() method');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/v1/non_existent_route';
$req404 = new Request();
$res404 = new $testResponse();
$router->resolve($req404, $res404);
assertTest($res404->code === 404, 'API router returns HTTP 404 for unknown route');
assertTest($res404->jsonPayload['error']['code'] === 'NOT_FOUND', 'API 404 returns NOT_FOUND code');

echo "\nAll Unit 01 tests passed successfully!\n";
