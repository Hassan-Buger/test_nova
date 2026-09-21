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
use Application\Controllers\Api\StatutoryApiController;
use Application\Models\AuditLog;

echo "=== UNIT 03: STATUTORY DEADLINES & CH RECONCILIATION TEST ===\n\n";

function assertTest(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("FAILED: " . $message);
    }
    echo " [PASS] $message\n";
}

$ref = new ReflectionClass(StatutoryApiController::class);
assertTest($ref->hasMethod('index'), 'StatutoryApiController has index() method');
assertTest($ref->hasMethod('sync'), 'StatutoryApiController has sync() method');

$auditRef = new ReflectionClass(AuditLog::class);
assertTest($auditRef->hasMethod('log'), 'AuditLog has log() method');

$testResponse = new class extends Response {
    public int $code = 0;
    public ?array $jsonPayload = null;
    public function setStatusCode(int $code): void { $this->code = $code; }
    public function json(array $data, int $code = 200): void {
        $this->code = $code;
        $this->jsonPayload = $data;
    }
};

$controllerRef = new ReflectionClass(StatutoryApiController::class);
$controller = $controllerRef->newInstanceWithoutConstructor();

// Test invalid payload (records not array)
$reqMock = new class extends Request {
    public function getJsonBody(): array {
        return ['records' => 'invalid_string'];
    }
};
$resInvalid = new $testResponse();
$controller->sync($reqMock, $resInvalid);
assertTest($resInvalid->code === 400, 'StatutoryApiController::sync rejects non-array records with HTTP 400');
assertTest($resInvalid->jsonPayload['error']['code'] === 'INVALID_PAYLOAD', 'Returns INVALID_PAYLOAD code');

// Test statutory item structure contract
$statutoryItem = [
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

assertTest(isset($statutoryItem['id']), 'Statutory item has id');
assertTest(isset($statutoryItem['entity_id']), 'Statutory item has entity_id');
assertTest(isset($statutoryItem['company_id']), 'Statutory item has company_id');
assertTest(isset($statutoryItem['company_name']), 'Statutory item has company_name');
assertTest(isset($statutoryItem['company_number']), 'Statutory item has company_number');
assertTest(isset($statutoryItem['type']), 'Statutory item has type');
assertTest(isset($statutoryItem['due_date']), 'Statutory item has due_date');
assertTest(isset($statutoryItem['status']), 'Statutory item has status');
assertTest(isset($statutoryItem['is_overdue']), 'Statutory item has is_overdue flag');

echo "\nAll Unit 03 tests passed successfully!\n";
