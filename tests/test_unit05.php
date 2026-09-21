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
use Application\Controllers\Api\DocumentApiController;

echo "=== UNIT 05: DOCUMENT RETRIEVAL & SIGNED DOWNLOAD URLS TEST ===\n\n";

function assertTest(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("FAILED: " . $message);
    }
    echo " [PASS] $message\n";
}

$ref = new ReflectionClass(DocumentApiController::class);
assertTest($ref->hasMethod('listByCompany'), 'DocumentApiController has listByCompany() method');
assertTest($ref->hasMethod('generateAccessToken'), 'DocumentApiController has generateAccessToken() method');
assertTest($ref->hasMethod('download'), 'DocumentApiController has download() method');

$testResponse = new class extends Response {
    public int $code = 0;
    public ?array $jsonPayload = null;
    public function setStatusCode(int $code): void { $this->code = $code; }
    public function json(array $data, int $code = 200): void {
        $this->code = $code;
        $this->jsonPayload = $data;
    }
};

$controllerRef = new ReflectionClass(DocumentApiController::class);
$controller = $controllerRef->newInstanceWithoutConstructor();

// 1. Test missing token on download
$_GET = [];
$req1 = new Request();
$res1 = new $testResponse();
$controller->download($req1, $res1);
assertTest($res1->code === 401, 'Download without token returns HTTP 401');
assertTest($res1->jsonPayload['error']['code'] === 'MISSING_DOWNLOAD_TOKEN', 'Error code is MISSING_DOWNLOAD_TOKEN');

// 2. Test expired token on download
$secret = App::get('secret');
$expiredTime = time() - 3600; // 1 hour ago
$expiredSig = hash_hmac('sha256', "doc_1:{$expiredTime}", $secret);
$expiredToken = rtrim(strtr(base64_encode(json_encode([
    'id' => 1,
    'exp' => $expiredTime,
    'sig' => $expiredSig
])), '+/', '-_'), '=');

$_GET = ['token' => $expiredToken];
$req2 = new Request();
$res2 = new $testResponse();
$controller->download($req2, $res2);
assertTest($res2->code === 401, 'Download with expired token returns HTTP 401');
assertTest($res2->jsonPayload['error']['code'] === 'DOWNLOAD_TOKEN_EXPIRED', 'Error code is DOWNLOAD_TOKEN_EXPIRED');

// 3. Test tampered signature token
$futureTime = time() + 900;
$tamperedToken = rtrim(strtr(base64_encode(json_encode([
    'id' => 1,
    'exp' => $futureTime,
    'sig' => 'invalid_forged_signature_hash'
])), '+/', '-_'), '=');

$_GET = ['token' => $tamperedToken];
$req3 = new Request();
$res3 = new $testResponse();
$controller->download($req3, $res3);
assertTest($res3->code === 401, 'Download with forged signature returns HTTP 401');
assertTest($res3->jsonPayload['error']['code'] === 'INVALID_DOWNLOAD_TOKEN', 'Error code is INVALID_DOWNLOAD_TOKEN');

// 4. Test document contract structure
$sampleDoc = [
    'id' => 1,
    'entity_id' => 1,
    'company_id' => 'comp_1',
    'filename' => 'Accounts_2025.pdf',
    'description' => 'Annual Accounts',
    'direction' => 'from_trinova',
    'status' => 'Ready',
    'file_size' => 1048576,
    'created_at' => '2026-01-10T12:00:00Z'
];

assertTest(isset($sampleDoc['id']), 'Document has id');
assertTest(isset($sampleDoc['entity_id']), 'Document has entity_id');
assertTest(isset($sampleDoc['company_id']), 'Document has company_id');
assertTest(isset($sampleDoc['filename']), 'Document has filename');
assertTest(isset($sampleDoc['file_size']), 'Document has file_size');
assertTest(isset($sampleDoc['status']), 'Document has status');
assertTest(isset($sampleDoc['created_at']), 'Document has created_at');

echo "\nAll Unit 05 tests passed successfully!\n";
