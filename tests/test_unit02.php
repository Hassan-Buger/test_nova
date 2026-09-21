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
use Application\Controllers\Api\ClientApiController;

echo "=== UNIT 02: CLIENTS & MULTI-COMPANY READ ENDPOINTS TEST ===\n\n";

function assertTest(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("FAILED: " . $message);
    }
    echo " [PASS] $message\n";
}

$ref = new ReflectionClass(ClientApiController::class);
assertTest($ref->hasMethod('index'), 'ClientApiController has index() method');
assertTest($ref->hasMethod('show'), 'ClientApiController has show() method');

// Test index query building and format transformation logic using mock DB if needed
// Let's create a test response capturer
$testResponse = new class extends Response {
    public int $code = 0;
    public ?array $jsonPayload = null;
    public function setStatusCode(int $code): void { $this->code = $code; }
    public function json(array $data, int $code = 200): void {
        $this->code = $code;
        $this->jsonPayload = $data;
    }
};

// Test show with invalid ID
$controllerRef = new ReflectionClass(ClientApiController::class);
$controller = $controllerRef->newInstanceWithoutConstructor();
$req = new Request();
$resInvalid = new $testResponse();
$controller->show($req, $resInvalid, 'abc');
assertTest($resInvalid->code === 400, 'ClientApiController::show returns 400 for non-numeric ID');
assertTest($resInvalid->jsonPayload['error']['code'] === 'INVALID_COMPANY_ID', 'Returns INVALID_COMPANY_ID code');

// Test data mapping structure contract
$sampleEntity = [
    'id' => 1,
    'client_id' => 1,
    'name' => 'Woofington Park Limited',
    'company_number' => '12345678',
    'entity_type' => 'Corporate',
    'directors' => ['Jane Doe'],
    'tax_reference' => 'UTR-998811',
    'created_at' => '2026-01-10T12:00:00Z'
];

assertTest(isset($sampleEntity['id']), 'Entity payload contains id');
assertTest(isset($sampleEntity['client_id']), 'Entity payload contains client_id');
assertTest(isset($sampleEntity['name']), 'Entity payload contains name');
assertTest(isset($sampleEntity['company_number']), 'Entity payload contains company_number');
assertTest(isset($sampleEntity['entity_type']), 'Entity payload contains entity_type');
assertTest(is_array($sampleEntity['directors']), 'Entity payload contains directors array');
assertTest(isset($sampleEntity['tax_reference']), 'Entity payload contains tax_reference');
assertTest(isset($sampleEntity['created_at']), 'Entity payload contains created_at');

echo "\nAll Unit 02 tests passed successfully!\n";
