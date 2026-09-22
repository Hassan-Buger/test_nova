<?php
/**
 * Test suite to verify:
 * 1. Router positional argument handling for /verify/signature/{token}
 * 2. SigningController::verify execution with valid and invalid tokens
 * 3. Sealer checksum recording and status update
 * 4. Staff SignatureController auto-seal & view routes
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Application\Config\App;
use Application\Core\Database;
use Application\Core\Request;
use Application\Core\Response;
use Application\Core\Router;
use Application\Controllers\SigningController;
use Application\Controllers\Staff\SignatureController as StaffSignatureController;
use Application\Controllers\Staff\DocumentController as StaffDocumentController;
use Application\Models\SignatureRequest;
use Application\Models\SignatureSigner;
use Application\Models\SignatureAuditEvent;
use Application\Services\SignatureSealerService;

echo "=== DIGITAL SIGNATURE VERIFICATION & VIEW FIX TEST SUITE ===\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $desc, bool $condition): void {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] {$desc}\n";
        $passCount++;
    } else {
        echo " [FAIL] {$desc}\n";
        $failCount++;
    }
}

// Create mock statement and mock PDO for tests without live DB
class MockPDOStatement {
    public ?array $returnData = null;
    public function execute($params = null): bool { return true; }
    public function fetch($mode = null, ...$args): mixed { return $this->returnData; }
    public function fetchAll($mode = null, ...$args): array { return $this->returnData ? [$this->returnData] : []; }
    public function rowCount(): int { return 1; }
}

$mockStmt = new MockPDOStatement();

$mockPdo = new class($mockStmt) extends PDO {
    private MockPDOStatement $stmt;
    public function __construct(MockPDOStatement $stmt) { $this->stmt = $stmt; }
    public function prepare($query, $options = []): MockPDOStatement { return $this->stmt; }
    public function beginTransaction(): bool { return true; }
    public function commit(): bool { return true; }
    public function rollBack(): bool { return true; }
    public function lastInsertId(?string $name = null): string { return '1'; }
};

Database::setInstance($mockPdo);

// 1. Test Router argument passing
$router = new Router();
$router->get('/verify/signature/{token}', [SigningController::class, 'verify']);

// 2. Test SigningController::verify with invalid token (should render verify_not_found, NOT crash with 500)
$mockStmt->returnData = null; // simulate token not found in DB
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/verify/signature/nonexistent_test_token_123';

$req = new Request();
$res = new Response();

ob_start();
try {
    $router->resolve($req, $res);
    $output = ob_get_clean();
    $noCrash = true;
    $hasNotFoundText = str_contains($output, 'Certificate Not Found') || str_contains($output, 'nonexistent_test_token_123');
} catch (Throwable $e) {
    ob_end_clean();
    $noCrash = false;
    $hasNotFoundText = false;
    echo "Exception in /verify/signature: " . $e->getMessage() . "\n";
}

assertTest("1. Router handles /verify/signature/{token} without PHP 8 named parameter crash", $noCrash);
assertTest("2. /verify/signature with invalid token gracefully renders verify_not_found", $hasNotFoundText);

// 3. Test SigningController::verify with a valid request
$testQrToken = '1ece97de517ed1910614093dd985016a';
$mockStmt->returnData = [
    'id' => 2,
    'document_id' => 1,
    'title' => 'Signature Request: VAT return Q2.pdf',
    'status' => 'pending',
    'signing_order' => 'parallel',
    'qr_token' => $testQrToken,
    'original_filename' => 'VAT return Q2.pdf',
    'original_stored_path' => 'uploads/test.pdf',
    'original_checksum_sha256' => '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08',
    'signed_checksum_sha256' => null,
    'client_name' => 'Dun Client',
    'entity_name' => 'Dun Ltd',
    'creator_name' => 'Test Staff'
];

$_SERVER['REQUEST_URI'] = '/verify/signature/' . $testQrToken;
ob_start();
try {
    $router->resolve($req, $res);
    $validOutput = ob_get_clean();
    $validLoaded = str_contains($validOutput, 'VAT return Q2.pdf') && str_contains($validOutput, 'Cryptographically Sealed');
} catch (Throwable $e) {
    ob_end_clean();
    $validLoaded = false;
    echo "Exception for valid token: " . $e->getMessage() . "\n";
}

assertTest("3. /verify/signature with valid token renders certificate and checksums without crash", $validLoaded);

// 4. Test SignatureRequest::markCompleted signature with checksums
$sigReqModel = new SignatureRequest();
$reflection = new ReflectionMethod($sigReqModel, 'markCompleted');
$params = $reflection->getParameters();
assertTest("4. SignatureRequest::markCompleted accepts original and signed checksums", count($params) >= 4);

// 5. Test Sealer fallback and checksum recording
$dummyOrigPath = __DIR__ . '/test_dummy_orig.pdf';
$dummyOutputPath = __DIR__ . '/test_dummy_sealed.pdf';

$pdf = new \setasign\Fpdi\Fpdi();
$pdf->AddPage();
$pdf->SetFont('Helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Test Document for Sealing', 0, 1);
$pdf->Output('F', $dummyOrigPath);

$sampleRequest = [
    'id' => 2,
    'title' => 'Signature Request: VAT return Q2.pdf',
    'original_filename' => 'VAT return Q2.pdf',
    'qr_token' => $testQrToken,
    'status' => 'pending',
    'signing_order' => 'parallel'
];

$sampleSigners = [
    [
        'id' => 1,
        'name' => 'dun',
        'email' => 'moon123@gmail.com',
        'role' => 'signer',
        'status' => 'signed',
        'signed_at' => '2026-09-17 22:46:00',
        'ip_address' => '59.103.110.144'
    ]
];

$sealResult = SignatureSealerService::sealPdf(
    $dummyOrigPath,
    $dummyOutputPath,
    [],
    $sampleRequest,
    $sampleSigners,
    [],
    $testQrToken
);

assertTest("5. SignatureSealerService::sealPdf seals document and appends certificate", is_file($dummyOutputPath) && filesize($dummyOutputPath) > 0);
assertTest("6. SignatureSealerService::sealPdf produces valid SHA-256 pre-signing checksum", strlen($sealResult['original_checksum']) === 64);
assertTest("7. SignatureSealerService::sealPdf produces valid SHA-256 signed checksum", strlen($sealResult['signed_checksum']) === 64);

// Clean up test PDFs
@unlink($dummyOrigPath);
@unlink($dummyOutputPath);

// 8. Verify Staff controller has seal action and show handles auto-seal
$staffSigController = new ReflectionClass(StaffSignatureController::class);
assertTest("8. Staff SignatureController has seal action method", $staffSigController->hasMethod('seal'));

$staffDocController = new ReflectionClass(StaffDocumentController::class);
assertTest("9. Staff DocumentController has view action method", $staffDocController->hasMethod('view'));

// 9. Verify show.php contains view links for original and signed documents
$showView = file_get_contents(__DIR__ . '/../application/Views/staff/signatures/show.php');
assertTest("10. show.php has View Original PDF link", str_contains($showView, 'View Original PDF'));
assertTest("11. show.php has View Signed PDF link", str_contains($showView, 'View Signed PDF'));
assertTest("12. show.php has Download Sealed PDF link", str_contains($showView, 'Download Sealed PDF'));
assertTest("13. show.php has Finalize & Seal Document action", str_contains($showView, 'Finalize &amp; Seal Document'));
assertTest("14. show.php has Document Artifacts & Files card", str_contains($showView, 'Document Artifacts &amp; Files'));

echo "\n======================================================================\n";
echo "  SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "======================================================================\n";

if ($failCount > 0) {
    exit(1);
}
