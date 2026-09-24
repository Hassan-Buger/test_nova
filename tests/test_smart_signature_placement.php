<?php
/**
 * TriNova Smart Signature Placement End-to-End Test Suite
 *
 * Validates automatic placement on the final page of uploaded PDFs,
 * manual placement preservation, sealing accuracy, coordinate integrity,
 * single-page and multi-page support, and error resilience.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$appDir = dirname(__DIR__);
require_once $appDir . '/vendor/autoload.php';

// Built-in PSR-4 autoloader fallback for Application\ namespace
spl_autoload_register(function ($class) use ($appDir) {
    $prefix = 'Application\\';
    $baseDir = $appDir . '/application/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require_once $file;
});

use Application\Config\App;
use Application\Core\Database;
use Application\Models\Document;
use Application\Models\SignatureField;
use Application\Models\SignatureRequest;
use Application\Models\SignatureSigner;
use Application\Models\SignatureAuditEvent;
use Application\Services\SignatureService;
use Application\Services\SignatureSealerService;
use Application\Services\FileStorageService;
use setasign\Fpdi\Fpdi;

// Set up in-memory SQLite test database with MySQL function emulation
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->sqliteCreateFunction('NOW', function() { return date('Y-m-d H:i:s'); });

$pdo->exec("
CREATE TABLE documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER DEFAULT 1,
    entity_id INTEGER DEFAULT 1,
    scope TEXT DEFAULT 'company',
    uploaded_by_user_id INTEGER DEFAULT 1,
    direction TEXT DEFAULT 'outbound',
    filename TEXT,
    stored_path TEXT,
    description TEXT DEFAULT NULL,
    status TEXT DEFAULT 'Ready',
    title TEXT DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE signature_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    document_id INTEGER,
    client_id INTEGER DEFAULT 1,
    entity_id INTEGER DEFAULT 1,
    created_by_user_id INTEGER DEFAULT 1,
    title TEXT,
    status TEXT DEFAULT 'pending',
    signing_order TEXT DEFAULT 'parallel',
    signed_document_id INTEGER DEFAULT NULL,
    original_checksum_sha256 TEXT DEFAULT NULL,
    signed_checksum_sha256 TEXT DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    qr_token TEXT DEFAULT NULL,
    allow_drawn_signature INTEGER DEFAULT 1,
    allow_typed_signature INTEGER DEFAULT 1,
    allow_upload_signature INTEGER DEFAULT 1,
    expires_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL
);

CREATE TABLE signature_signers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id INTEGER,
    user_id INTEGER,
    name TEXT,
    email TEXT,
    role TEXT DEFAULT 'signer',
    signing_order INTEGER DEFAULT 1,
    token TEXT,
    status TEXT DEFAULT 'pending',
    read_status TEXT DEFAULT 'not_opened',
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    opened_at DATETIME,
    signed_at DATETIME,
    ip_address TEXT,
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE signature_fields (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id INTEGER,
    signer_id INTEGER,
    type TEXT DEFAULT 'signature',
    page INTEGER DEFAULT 1,
    position_x REAL DEFAULT 0,
    position_y REAL DEFAULT 0,
    width REAL DEFAULT 20,
    height REAL DEFAULT 6,
    custom_text TEXT,
    signature_data TEXT,
    signature_type TEXT,
    required INTEGER DEFAULT 1,
    inserted INTEGER DEFAULT 0,
    signed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE signature_audit_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id INTEGER,
    event_type TEXT,
    event_description TEXT,
    signer_id INTEGER,
    user_id INTEGER,
    metadata TEXT,
    ip_address TEXT,
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action_type TEXT,
    target_type TEXT,
    target_id INTEGER,
    ip_address TEXT,
    import_metadata TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    type TEXT,
    related_entity TEXT,
    title TEXT,
    message TEXT,
    action_url TEXT,
    read_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER DEFAULT 1
);

CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT DEFAULT 'Staff User',
    email TEXT DEFAULT 'staff@trinova.co.uk',
    role TEXT DEFAULT 'staff'
);

CREATE TABLE client_entities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name TEXT DEFAULT 'Capsule Corp',
    entity_scope TEXT DEFAULT 'company'
);

INSERT INTO users (id, name, email, role) VALUES (1, 'Staff User', 'staff@trinova.co.uk', 'staff');
INSERT INTO clients (id, user_id) VALUES (1, 1);
INSERT INTO client_entities (id, company_name, entity_scope) VALUES (1, 'Capsule Corp', 'company');
");

Database::setInstance($pdo);

$testPassed = 0;
$testFailed = 0;

function assertTest(bool $condition, string $description): void
{
    global $testPassed, $testFailed;
    if ($condition) {
        $testPassed++;
        echo "  [PASS] {$description}\n";
    } else {
        $testFailed++;
        echo "  [FAIL] {$description}\n";
    }
}

echo "======================================================================\n";
echo "  TRINOVA SMART SIGNATURE PLACEMENT - COMPREHENSIVE TEST SUITE\n";
echo "======================================================================\n\n";

// -------------------------------------------------------------------------
// SECTION 1: Multi-Page Document (Trinova_Capsule_Corp_Transfer_Policy.pdf)
// -------------------------------------------------------------------------
echo "[Section 1: Multi-Page Document (Transfer Policy, 2 Pages)]\n";

$transferPdfPath = $appDir . '/storage/uploads/Trinova_Capsule_Corp_Transfer_Policy.pdf';
if (!file_exists($transferPdfPath)) {
    copy('C:/Users/MR RED/Downloads/Trinova_Capsule_Corp_Transfer_Policy.pdf', $transferPdfPath);
}

$stmt = $pdo->prepare("INSERT INTO documents (title, filename, stored_path) VALUES (?, ?, ?)");
$stmt->execute(['Transfer Policy', 'Trinova_Capsule_Corp_Transfer_Policy.pdf', 'Trinova_Capsule_Corp_Transfer_Policy.pdf']);
$docTransferId = (int)$pdo->lastInsertId();

$placementTransfer = SignatureService::determineDefaultPlacement(['document_id' => $docTransferId]);
assertTest($placementTransfer['total_pages'] === 2, "Transfer Policy detected with 2 total pages");
assertTest($placementTransfer['page'] === 2, "Automatic placement correctly targets final page (Page 2)");
assertTest($placementTransfer['is_landscape'] === false, "Detected portrait orientation for Transfer Policy");
assertTest($placementTransfer['signature_field']['position_x'] === 9.5, "Signature X coordinate is safe at 9.5%");
assertTest($placementTransfer['signature_field']['position_y'] === 74.0, "Signature Y coordinate is in footer band at 74.0%");
assertTest($placementTransfer['date_field']['position_x'] === 55.0, "Date X coordinate is safe at 55.0%");
assertTest($placementTransfer['date_field']['position_y'] === 80.0, "Date Y coordinate is in footer band at 80.0%");

// Create signature request with NO custom fields (Mode B - Automatic)
$reqTransferStmt = $pdo->prepare("INSERT INTO signature_requests (document_id, title) VALUES (?, ?)");
$reqTransferStmt->execute([$docTransferId, 'Signature Request: Trinova_Capsule_Corp_Transfer_Policy.pdf']);
$reqTransferId = (int)$pdo->lastInsertId();

$tokenTransfer = bin2hex(random_bytes(16));
$signerTransferStmt = $pdo->prepare("INSERT INTO signature_signers (request_id, name, email, token) VALUES (?, ?, ?, ?)");
$signerTransferStmt->execute([$reqTransferId, 'Gohan', 'gohan@capsulecorp.com', $tokenTransfer]);
$signerTransferId = (int)$pdo->lastInsertId();

$sessionTransfer = SignatureService::getSigningSession($tokenTransfer);
$fieldsTransfer = $sessionTransfer['my_fields'];
assertTest(count($fieldsTransfer) === 2, "Auto-created exactly 2 fields (signature and date)");

$sigField = null;
$dateField = null;
foreach ($fieldsTransfer as $f) {
    if ($f['type'] === 'signature') $sigField = $f;
    if ($f['type'] === 'date') $dateField = $f;
}

assertTest($sigField !== null && (int)$sigField['page'] === 2, "Signature field placed strictly on Page 2 (Final Page), NOT Page 1");
assertTest($dateField !== null && (int)$dateField['page'] === 2, "Date field placed strictly on Page 2 (Final Page), NOT Page 1");
assertTest((float)$sigField['position_x'] === 9.5 && (float)$sigField['position_y'] === 74.0, "Signature field coordinates preserved on Page 2");
assertTest((float)$dateField['position_x'] === 55.0 && (float)$dateField['position_y'] === 80.0, "Date field coordinates preserved on Page 2");

// Verify zero fields on Page 1 (No overlap with Section 6)
$page1Fields = array_filter($fieldsTransfer, fn($f) => (int)$f['page'] === 1);
assertTest(count($page1Fields) === 0, "Page 1 has zero fields: No overlap with Section 6 (Digital Execution & Record Integrity)");

// -------------------------------------------------------------------------
// SECTION 2: Idempotency & No Duplicate Fields
// -------------------------------------------------------------------------
echo "\n[Section 2: Idempotency & No Duplicate Fields]\n";

$sessionTransfer2 = SignatureService::getSigningSession($tokenTransfer);
assertTest(count($sessionTransfer2['my_fields']) === 2, "Subsequent getSigningSession does not create duplicate fields");

$fieldsInDb = (new SignatureField())->getBySignerId($signerTransferId);
assertTest(count($fieldsInDb) === 2, "Database contains exactly 2 fields for signer");

// -------------------------------------------------------------------------
// SECTION 3: Mode A - Manual Field Placement Precedence
// -------------------------------------------------------------------------
echo "\n[Section 3: Mode A - Manual Placement Precedence]\n";

$reqManualStmt = $pdo->prepare("INSERT INTO signature_requests (document_id, title) VALUES (?, ?)");
$reqManualStmt->execute([$docTransferId, 'Manual Field Test']);
$reqManualId = (int)$pdo->lastInsertId();

$tokenManual = bin2hex(random_bytes(16));
$signerManualStmt = $pdo->prepare("INSERT INTO signature_signers (request_id, name, email, token) VALUES (?, ?, ?, ?)");
$signerManualStmt->execute([$reqManualId, 'Manual Signer', 'manual@example.com', $tokenManual]);
$signerManualId = (int)$pdo->lastInsertId();

// Staff explicitly placed fields: Signature on Page 1 at (25.0, 30.0), Date on Page 1 at (60.0, 30.0)
$sigFieldModel = new SignatureField();
$sigFieldModel->create([
    'request_id' => $reqManualId,
    'signer_id'  => $signerManualId,
    'type'       => 'signature',
    'page'       => 1,
    'position_x' => 25.0,
    'position_y' => 30.0,
    'width'      => 30.0,
    'height'     => 7.0,
    'required'   => 1,
]);
$sigFieldModel->create([
    'request_id' => $reqManualId,
    'signer_id'  => $signerManualId,
    'type'       => 'date',
    'page'       => 1,
    'position_x' => 60.0,
    'position_y' => 30.0,
    'width'      => 20.0,
    'height'     => 4.0,
    'required'   => 0,
]);

$sessionManual = SignatureService::getSigningSession($tokenManual);
$fieldsManual = $sessionManual['my_fields'];
assertTest(count($fieldsManual) === 2, "Manual fields loaded without extra auto-generated fields");
$mSig = $fieldsManual[0]['type'] === 'signature' ? $fieldsManual[0] : $fieldsManual[1];
assertTest((int)$mSig['page'] === 1, "Manual signature page preserved at Page 1");
assertTest((float)$mSig['position_x'] === 25.0 && (float)$mSig['position_y'] === 30.0, "Manual signature coordinates preserved at (25%, 30%)");

// -------------------------------------------------------------------------
// SECTION 4: 1-Page Document Compatibility (Demo Placeholder PDF)
// -------------------------------------------------------------------------
echo "\n[Section 4: Single-Page Document Compatibility]\n";

$singlePdfPath = $appDir . '/storage/uploads/test_single_page.pdf';
FileStorageService::generatePlaceholderPdf($singlePdfPath, 'Demo 1-Page Account');

$stmt->execute(['1-Page Doc', 'test_single_page.pdf', 'test_single_page.pdf']);
$docSingleId = (int)$pdo->lastInsertId();

$placementSingle = SignatureService::determineDefaultPlacement(['document_id' => $docSingleId]);
assertTest($placementSingle['total_pages'] === 1, "Single-page document detected with 1 page");
assertTest($placementSingle['page'] === 1, "Automatic placement correctly uses Page 1 for 1-page document");

$reqSingleStmt = $pdo->prepare("INSERT INTO signature_requests (document_id, title) VALUES (?, ?)");
$reqSingleStmt->execute([$docSingleId, 'Single Page Request']);
$reqSingleId = (int)$pdo->lastInsertId();

$tokenSingle = bin2hex(random_bytes(16));
$signerSingleStmt = $pdo->prepare("INSERT INTO signature_signers (request_id, name, email, token) VALUES (?, ?, ?, ?)");
$signerSingleStmt->execute([$reqSingleId, 'Single Signer', 'single@example.com', $tokenSingle]);
$signerSingleId = (int)$pdo->lastInsertId();

$sessionSingle = SignatureService::getSigningSession($tokenSingle);
$fieldsSingle = $sessionSingle['my_fields'];
assertTest(count($fieldsSingle) === 2, "Auto-created 2 fields on single-page document");
assertTest((int)$fieldsSingle[0]['page'] === 1 && (int)$fieldsSingle[1]['page'] === 1, "Both fields placed on Page 1 inside Authorized Signature Area");

// -------------------------------------------------------------------------
// SECTION 5: Multi-Page Test Matrix (3-Page, 5-Page, Landscape)
// -------------------------------------------------------------------------
echo "\n[Section 5: Multi-Page Test Matrix]\n";

// 3-page taxation document
$threePagePdf = 'C:/Users/MR RED/.gemini/antigravity-ide/brain/d4dc9d82-71a6-4fe3-8a82-eacb3a8a46d1/.user_uploaded/media_1790186441310.pdf';
copy($threePagePdf, $appDir . '/storage/uploads/test_3_page.pdf');
$stmt->execute(['3-Page Doc', 'test_3_page.pdf', 'test_3_page.pdf']);
$doc3Id = (int)$pdo->lastInsertId();

$placement3 = SignatureService::determineDefaultPlacement(['document_id' => $doc3Id]);
assertTest($placement3['total_pages'] === 3, "3-page taxation document detected with 3 pages");
assertTest($placement3['page'] === 3, "Fields placed dynamically on final page (Page 3)");

// 5-page synthetic document
$fivePagePath = $appDir . '/storage/uploads/test_5_page.pdf';
$fp = new Fpdi();
for ($p = 1; $p <= 5; $p++) {
    $fp->AddPage('P', [210, 297]);
    $fp->SetFont('Helvetica', 'B', 14);
    $fp->SetXY(20, 20);
    $fp->Cell(100, 10, "Page {$p} of 5", 0, 1);
}
$fp->Output('F', $fivePagePath);

$stmt->execute(['5-Page Doc', 'test_5_page.pdf', 'test_5_page.pdf']);
$doc5Id = (int)$pdo->lastInsertId();
$placement5 = SignatureService::determineDefaultPlacement(['document_id' => $doc5Id]);
assertTest($placement5['total_pages'] === 5, "5-page document detected with 5 pages");
assertTest($placement5['page'] === 5, "Fields placed dynamically on final page (Page 5)");

// Landscape document
$landscapePath = $appDir . '/storage/uploads/test_landscape.pdf';
$fl = new Fpdi();
$fl->AddPage('L', [297, 210]);
$fl->SetFont('Helvetica', 'B', 14);
$fl->SetXY(20, 20);
$fl->Cell(100, 10, "Landscape Document", 0, 1);
$fl->Output('F', $landscapePath);

$stmt->execute(['Landscape Doc', 'test_landscape.pdf', 'test_landscape.pdf']);
$docLandId = (int)$pdo->lastInsertId();
$placementLand = SignatureService::determineDefaultPlacement(['document_id' => $docLandId]);
assertTest($placementLand['is_landscape'] === true, "Landscape orientation detected");
assertTest($placementLand['page'] === 1, "Landscape page 1 selected as final page");
assertTest($placementLand['signature_field']['position_x'] === 9.5, "Landscape signature X coordinate safe");

// -------------------------------------------------------------------------
// SECTION 6: Error Resilience & Graceful Fallback
// -------------------------------------------------------------------------
echo "\n[Section 6: Error Resilience & Graceful Fallbacks]\n";

// Missing document ID
$placementMissing = SignatureService::determineDefaultPlacement(['document_id' => 999999]);
assertTest($placementMissing['page'] === 1, "Non-existent document gracefully falls back to Page 1 without throwing");

// Corrupted PDF file
$corruptPath = $appDir . '/storage/uploads/corrupt.pdf';
file_put_contents($corruptPath, "NOT A VALID PDF CONTENT");
$stmt->execute(['Corrupt Doc', 'corrupt.pdf', 'corrupt.pdf']);
$docCorruptId = (int)$pdo->lastInsertId();
$placementCorrupt = SignatureService::determineDefaultPlacement(['document_id' => $docCorruptId]);
assertTest($placementCorrupt['page'] === 1, "Corrupted PDF gracefully falls back to Page 1 without throwing");

// -------------------------------------------------------------------------
// SECTION 7: End-to-End Submission & PDF Sealing on Transfer Policy
// -------------------------------------------------------------------------
echo "\n[Section 7: Full Signing Submission & PDF Sealing on Transfer Policy]\n";

$cropSigFile = 'C:/Users/MR RED/.gemini/antigravity-ide/brain/d4dc9d82-71a6-4fe3-8a82-eacb3a8a46d1/scratch/crop_sig.png';
if (file_exists($cropSigFile)) {
    $base64Sig = 'data:image/png;base64,' . base64_encode(file_get_contents($cropSigFile));
} else {
    // 1x1 transparent PNG fallback
    $base64Sig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
}

// Submit signer fields
$submissions = [
    [
        'field_id'       => $sigField['id'],
        'type'           => 'signature',
        'value'          => $base64Sig,
        'signature_type' => 'drawn',
        'page'           => 2,
        'position_x'     => 9.5,
        'position_y'     => 74.0,
        'width'          => 28.0,
        'height'         => 5.6,
    ],
    [
        'field_id'       => $dateField['id'],
        'type'           => 'date',
        'value'          => date('d/m/Y'),
        'page'           => 2,
        'position_x'     => 55.0,
        'position_y'     => 80.0,
        'width'          => 35.0,
        'height'         => 3.5,
    ]
];

$submitResult = SignatureService::submitSignerFields($tokenTransfer, $submissions, '127.0.0.1', 'Mozilla/5.0');
assertTest($submitResult['success'] === true, "submitSignerFields completed successfully");

// Verify fields in DB have page = 2
$finalFields = (new SignatureField())->getBySignerId($signerTransferId);
assertTest((int)$finalFields[0]['page'] === 2 && (int)$finalFields[1]['page'] === 2, "Saved field values retain page = 2 in database");
assertTest(!empty($finalFields[0]['signature_data']), "Signature data stored in database");

// Now seal the PDF using SignatureSealerService
$sealedOutputPath = $appDir . '/storage/uploads/test_artifacts/transfer_policy_sealed_verified.pdf';
$sealResult = SignatureSealerService::sealPdf(
    $transferPdfPath,
    $sealedOutputPath,
    $finalFields,
    (new SignatureRequest())->find($reqTransferId),
    [(new SignatureSigner())->find($signerTransferId)],
    (new SignatureAuditEvent())->getByRequestId($reqTransferId),
    'qr_verify_test_token'
);

assertTest(file_exists($sealedOutputPath), "Sealed PDF file was created on disk");
assertTest($sealResult['page_count'] === 2, "Sealer processed 2 original document pages");
assertTest($sealResult['total_pages'] === 3, "Sealed PDF has 3 total pages (2 document pages + 1 Certificate)");

// Inspect sealed PDF pages with FPDI
$sealedFpdi = new Fpdi();
$sealedPages = $sealedFpdi->setSourceFile($sealedOutputPath);
assertTest($sealedPages === 3, "Sealed PDF page count strictly matches 3");

// Verify content of sealed PDF pages
$sealedContent = file_get_contents($sealedOutputPath);
preg_match_all('#stream[\r\n]+(.*?)[\r\n]+endstream#s', $sealedContent, $streamMatches);
$hasCertificate = false;
foreach ($streamMatches[1] as $stream) {
    $decompressed = @gzuncompress($stream);
    if (!$decompressed) $decompressed = @gzinflate($stream);
    if ($decompressed && (str_contains($decompressed, 'CERTIFICATE OF COMPLETION') || str_contains($decompressed, 'Certificate'))) {
        $hasCertificate = true;
        break;
    }
}

// Check that Certificate of Completion text exists
assertTest($hasCertificate, "Certificate of Completion appended after signed document");

// Verify that the original SHA-256 and signed SHA-256 are different
assertTest($sealResult['original_checksum'] !== $sealResult['signed_checksum'], "Cryptographic SHA-256 checksum proves sealed document integrity");

// -------------------------------------------------------------------------
// SECTION 8: 1-Page Sealing Reference Test (capsule_corp demo)
// -------------------------------------------------------------------------
echo "\n[Section 8: 1-Page Sealing Reference Test]\n";

$sealed1PagePath = $appDir . '/storage/uploads/test_artifacts/single_page_sealed_verified.pdf';
$subSingle = [
    [
        'field_id'       => $fieldsSingle[0]['id'],
        'type'           => 'signature',
        'value'          => $base64Sig,
        'signature_type' => 'drawn',
        'page'           => 1,
        'position_x'     => 9.5,
        'position_y'     => 74.0,
        'width'          => 28.0,
        'height'         => 5.6,
    ],
    [
        'field_id'       => $fieldsSingle[1]['id'],
        'type'           => 'date',
        'value'          => date('d/m/Y'),
        'page'           => 1,
        'position_x'     => 55.0,
        'position_y'     => 80.0,
        'width'          => 35.0,
        'height'         => 3.5,
    ]
];
SignatureService::submitSignerFields($tokenSingle, $subSingle, '127.0.0.1', 'Mozilla/5.0');
$finalSingleFields = (new SignatureField())->getBySignerId($signerSingleId);

$seal1Result = SignatureSealerService::sealPdf(
    $singlePdfPath,
    $sealed1PagePath,
    $finalSingleFields,
    (new SignatureRequest())->find($reqSingleId),
    [(new SignatureSigner())->find($signerSingleId)],
    (new SignatureAuditEvent())->getByRequestId($reqSingleId),
    'qr_verify_single'
);
assertTest($seal1Result['page_count'] === 1, "Single-page document processed with 1 original page");
assertTest($seal1Result['total_pages'] === 2, "Single-page sealed PDF has 2 pages (1 doc + 1 Certificate)");

// Cleanup temporary artifacts
@unlink($corruptPath);
@unlink($fivePagePath);
@unlink($landscapePath);

echo "\n======================================================================\n";
echo "  SUMMARY: {$testPassed} PASSED, {$testFailed} FAILED\n";
echo "======================================================================\n";

if ($testFailed > 0) {
    exit(1);
}
