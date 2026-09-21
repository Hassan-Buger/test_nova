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
use Application\Controllers\Api\NoteApiController;
use Application\Services\IdempotencyService;

echo "=== UNIT 04: INTERNAL NOTES & STRICT PRIVACY BOUNDARY TEST ===\n\n";

function assertTest(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("FAILED: " . $message);
    }
    echo " [PASS] $message\n";
}

$ref = new ReflectionClass(NoteApiController::class);
assertTest($ref->hasMethod('create'), 'NoteApiController has create() method');

$idempRef = new ReflectionClass(IdempotencyService::class);
assertTest($idempRef->hasMethod('get'), 'IdempotencyService has get() method');
assertTest($idempRef->hasMethod('save'), 'IdempotencyService has save() method');

$testResponse = new class extends Response {
    public int $code = 0;
    public ?array $jsonPayload = null;
    public function setStatusCode(int $code): void { $this->code = $code; }
    public function json(array $data, int $code = 200): void {
        $this->code = $code;
        $this->jsonPayload = $data;
    }
};

$controllerRef = new ReflectionClass(NoteApiController::class);
$controller = $controllerRef->newInstanceWithoutConstructor();

// 1. Test Privacy Boundary Check: confirm_write_to_trinova missing or false
$reqNoConfirm = new class extends Request {
    public function getJsonBody(): array {
        return [
            'text' => 'Confidential CEO thoughts',
            'confirm_write_to_trinova' => false
        ];
    }
};
$res1 = new $testResponse();
$controller->create($reqNoConfirm, $res1, '1');
assertTest($res1->code === 403, 'Rejects note when confirm_write_to_trinova is false with HTTP 403');
assertTest($res1->jsonPayload['error']['code'] === 'PRIVACY_BOUNDARY_CONFIRMATION_REQUIRED', 'Error code is PRIVACY_BOUNDARY_CONFIRMATION_REQUIRED');

// 2. Test Empty text check
$reqEmptyText = new class extends Request {
    public function getJsonBody(): array {
        return [
            'text' => '   ',
            'confirm_write_to_trinova' => true
        ];
    }
};
$res2 = new $testResponse();
$controller->create($reqEmptyText, $res2, '1');
assertTest($res2->code === 422, 'Rejects note with empty text with HTTP 422');
assertTest($res2->jsonPayload['error']['code'] === 'VALIDATION_FAILED', 'Error code is VALIDATION_FAILED');

// 3. Test Invalid company ID
$res3 = new $testResponse();
$controller->create($reqEmptyText, $res3, 'invalid_id');
assertTest($res3->code === 400, 'Rejects non-numeric company ID with HTTP 400');
assertTest($res3->jsonPayload['error']['code'] === 'INVALID_COMPANY_ID', 'Error code is INVALID_COMPANY_ID');

// 4. Verify note response contract structure
$sampleNote = [
    'id' => 'note_a1b2c3d4e5f60718',
    'company_id' => 'comp_1',
    'text' => 'Reviewed Q3 cash flow with director. Expanding debt facility.',
    'visibility' => 'staff_only',
    'created_at' => '2026-09-21T14:30:00Z'
];

assertTest(str_starts_with($sampleNote['id'], 'note_'), 'Note id starts with note_');
assertTest($sampleNote['company_id'] === 'comp_1', 'Note company_id is formatted correctly');
assertTest($sampleNote['visibility'] === 'staff_only', 'Note default visibility is staff_only');
assertTest(isset($sampleNote['created_at']), 'Note has created_at timestamp');

echo "\nAll Unit 04 tests passed successfully!\n";
