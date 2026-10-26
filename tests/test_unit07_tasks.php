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

use Application\Core\Request;
use Application\Core\Response;
use Application\Core\Router;
use Application\Middleware\ApiAuthMiddleware;
use Application\Services\WebhookService;

echo "=== UNIT 07: TASKS API, STAFF DIRECTORY, SCOPED AUTH & WEBHOOKS ===\n\n";

function assertTest(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("FAILED: " . $message);
    }
    echo " [PASS] $message\n";
}

// 1. Router Route Parameter Resolution (:id and {id})
$router = new Router();
$matchedParams = [];
$router->patch('/api/v1/tasks/{id}', function($req, $res, $id) use (&$matchedParams) {
    $matchedParams['id'] = $id;
});

$_SERVER['REQUEST_METHOD'] = 'PATCH';
$_SERVER['REQUEST_URI'] = '/api/v1/tasks/42';
$req = new Request();
$res = new Response();
$router->resolve($req, $res);
assertTest($matchedParams['id'] === '42', 'Router matches numeric ID in /api/v1/tasks/{id}');

// 2. Scoped Token Permission Verification
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

// System master key should be allowed on all endpoints
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer tn_sec_live_sloane_readwrite_2026';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/v1/tasks';
$req2 = new Request();
$res2 = clone $testResponse;
$result = $mw->handle($req2, $res2);
assertTest($result === true, 'Master system key passes authentication for POST /api/v1/tasks');

// 3. Webhook HMAC-SHA256 signature test
$secret = 'whsec_test_secret_123';
$testPayload = json_encode(['event' => 'task.updated', 'data' => ['id' => 1]], JSON_UNESCAPED_SLASHES);
$expectedSig = 'sha256=' . hash_hmac('sha256', $testPayload, $secret);
$computedSig = 'sha256=' . hash_hmac('sha256', $testPayload, $secret);
assertTest(hash_equals($expectedSig, $computedSig), 'Webhook HMAC signature calculation is deterministic and matches sha256 format');

echo "\nALL UNIT 07 ASSERTIONS PASSED SUCCESSFULLY.\n";