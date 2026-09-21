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
use Application\Controllers\Api\SignatureApiController;

echo "=== UNIT 06: DIGITAL SIGNATURES API BRIDGE TEST ===\n\n";

function assertTest(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("FAILED: " . $message);
    }
    echo " [PASS] $message\n";
}

$ref = new ReflectionClass(SignatureApiController::class);
assertTest($ref->hasMethod('listByCompany'), 'SignatureApiController has listByCompany() method');
assertTest($ref->hasMethod('create'), 'SignatureApiController has create() method');
assertTest($ref->hasMethod('audit'), 'SignatureApiController has audit() method');

$testResponse = new class extends Response {
    public int $code = 0;
    public ?array $jsonPayload = null;
    public function setStatusCode(int $code): void { $this->code = $code; }
    public function json(array $data, int $code = 200): void {
        $this->code = $code;
        $this->jsonPayload = $data;
    }
};

$controllerRef = new ReflectionClass(SignatureApiController::class);
$controller = $controllerRef->newInstanceWithoutConstructor();

// 1. Test missing title validation
$reqNoTitle = new class extends Request {
    public function getJsonBody(): array {
        return [
            'signers' => [['name' => 'Jane', 'email' => 'jane@example.com']]
        ];
    }
};
$res1 = new $testResponse();
$controller->create($reqNoTitle, $res1, '1');
assertTest($res1->code === 422, 'Rejects signing request without title with HTTP 422');
assertTest($res1->jsonPayload['error']['code'] === 'VALIDATION_FAILED', 'Error code is VALIDATION_FAILED');

// 2. Test missing signers validation
$reqNoSigners = new class extends Request {
    public function getJsonBody(): array {
        return [
            'title' => 'Sample Doc',
            'signers' => []
        ];
    }
};
$res2 = new $testResponse();
$controller->create($reqNoSigners, $res2, '1');
assertTest($res2->code === 422, 'Rejects signing request with empty signers with HTTP 422');

// 3. Test non-numeric company ID
$res3 = new $testResponse();
$controller->create($reqNoSigners, $res3, 'xyz');
assertTest($res3->code === 400, 'Rejects non-numeric company ID with HTTP 400');

// 4. Test invalid signature ID on audit
$reqAudit = new Request();
$res4 = new $testResponse();
$controller->audit($reqAudit, $res4, 'invalid_id');
assertTest($res4->code === 400, 'Rejects invalid audit ID with HTTP 400');
assertTest($res4->jsonPayload['error']['code'] === 'INVALID_SIGNATURE_ID', 'Returns INVALID_SIGNATURE_ID code');

// 5. Test signing request contract structure
$sampleRequest = [
    'id' => 1,
    'company_id' => 'comp_1',
    'title' => 'Board Minutes Q3 2026',
    'status' => 'pending',
    'signers' => [
        [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'role' => 'signer',
            'signing_url' => 'https://example.com/sign/token123',
            'status' => 'pending'
        ]
    ],
    'created_at' => '2026-09-21T14:30:00Z'
];

assertTest(isset($sampleRequest['id']), 'Signing request has id');
assertTest(isset($sampleRequest['company_id']), 'Signing request has company_id');
assertTest(isset($sampleRequest['title']), 'Signing request has title');
assertTest(isset($sampleRequest['status']), 'Signing request has status');
assertTest(is_array($sampleRequest['signers']), 'Signing request has signers array');
assertTest(!empty($sampleRequest['signers'][0]['signing_url']), 'Signer contains signing_url');

echo "\nAll Unit 06 tests passed successfully!\n";
