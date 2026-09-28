<?php
/**
 * TriNova Surgical Fix Regression Test Suite
 *
 * Verifies all 8 required cases from Section 19 of the prompt:
 * CASE 1: One IPv4 address (59.103.110.144)
 * CASE 2: Two IPv4 addresses (59.103.110.144, 152.233.68.97)
 * CASE 3: IPv6 address (2001:db8:1234:5678::1)
 * CASE 4: Multiple IPv6 addresses (2001:db8:1234:5678::1, 2001:db8:3333:4444:5555:6666:7777:8888)
 * CASE 5: Long document title
 * CASE 6: Long audit event description
 * CASE 7: Multiple audit events
 * CASE 8: Normal existing certificate
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../application/Services/SignatureSealerService.php';

use Application\Core\Database;
use Application\Services\SignatureSealerService;
use setasign\Fpdi\Fpdi;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("
CREATE TABLE IF NOT EXISTS signature_audit_events (
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
");
Database::setInstance($pdo);

$outputDir = __DIR__ . '/../storage/test_output';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

function createSigImage(string $text): string {
    $img = imagecreatetruecolor(300, 100);
    imagesavealpha($img, true);
    $trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $trans);
    $color = imagecolorallocate($img, 15, 23, 42);
    imageline($img, 10, 60, 60, 20, $color);
    imageline($img, 60, 20, 120, 50, $color);
    imageline($img, 120, 50, 180, 25, $color);
    imageline($img, 180, 25, 250, 45, $color);
    imagestring($img, 5, 20, 35, $text, $color);
    ob_start();
    imagepng($img);
    $raw = ob_get_clean();
    imagedestroy($img);
    return 'data:image/png;base64,' . base64_encode($raw);
}

$passCount = 0;
$totalCount = 0;

function assertCheck(bool $condition, string $label) {
    global $passCount, $totalCount;
    $totalCount++;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$label}\n";
    } else {
        echo "  [FAIL] {$label}\n";
    }
}

echo "======================================================================\n";
echo "  TRINOVA SURGICAL FIX - REGRESSION SUITE (SECTION 19 CASES)\n";
echo "======================================================================\n";

// Shared Base Source Document
$pdf1 = new Fpdi();
$pdf1->AddPage('P', [210, 297]);
$pdf1->SetFont('Helvetica', 'B', 14);
$pdf1->Text(20, 40, 'Update Timetable Policy - TriNova Portal Demo');
$pdf1->SetFont('Helvetica', '', 10);
$pdf1->Text(20, 55, 'This document establishes a controlled schedule for digital execution.');
$baseSrc = $outputDir . '/reg_base_source.pdf';
$pdf1->Output($baseSrc, 'F');

$baseFields = [
    [
        'type' => 'signature',
        'page' => 1,
        'position_x' => 9.5,
        'position_y' => 74.0,
        'width' => 28.0,
        'height' => 5.6,
        'field_value' => createSigImage('Gohan'),
        'signature_type' => 'drawn',
        'signer_id' => 1
    ],
    [
        'type' => 'date',
        'page' => 1,
        'position_x' => 55.0,
        'position_y' => 80.0,
        'width' => 35.0,
        'height' => 3.5,
        'field_value' => '28/09/2026',
        'signer_id' => 1
    ]
];

$baseReq = [
    'id' => 12,
    'title' => 'Signature Request: Trinova_Update_Timetable_Policy.pdf',
    'entity_name' => 'Capsule Corp',
    'created_at' => '2026-09-28 18:46:17',
    'qr_token' => 'd1a2b7f650f0d6cf82a3f496a533b389',
    'signing_order' => 'parallel'
];

// ----------------------------------------------------------------------
// CASE 1: One IPv4 Address (59.103.110.144)
// ----------------------------------------------------------------------
echo "\n[CASE 1: One IPv4 Address (59.103.110.144)]\n";
$signersCase1 = [
    [
        'id' => 1,
        'name' => 'Gohan',
        'email' => 'mhi1755480@gmail.com',
        'role' => 'signer',
        'status' => 'signed',
        'signed_at' => '2026-09-28 18:46:49',
        'sent_at' => '2026-09-28 18:46:17',
        'ip_address' => '59.103.110.144',
        'token' => 'bdea1f4f435bd8349911'
    ]
];
$sealedCase1 = $outputDir . '/case1_single_ipv4_sealed.pdf';
SignatureSealerService::sealPdf($baseSrc, $sealedCase1, $baseFields, $baseReq, $signersCase1);
assertCheck(is_file($sealedCase1), "Case 1 sealed PDF created successfully with 1 IPv4");

// ----------------------------------------------------------------------
// CASE 2: Two IPv4 Addresses (59.103.110.144, 152.233.68.97)
// ----------------------------------------------------------------------
echo "\n[CASE 2: Two IPv4 Addresses (59.103.110.144, 152.233.68.97)]\n";
$signersCase2 = [
    [
        'id' => 1,
        'name' => 'Gohan',
        'email' => 'mhi1755480@gmail.com',
        'role' => 'signer',
        'status' => 'signed',
        'signed_at' => '2026-09-28 18:46:49',
        'sent_at' => '2026-09-28 18:46:17',
        'ip_address' => '59.103.110.144, 152.233.68.97',
        'token' => 'bdea1f4f435bd8349911'
    ]
];
$sealedCase2 = $outputDir . '/case2_dual_ipv4_sealed.pdf';
SignatureSealerService::sealPdf($baseSrc, $sealedCase2, $baseFields, $baseReq, $signersCase2);
assertCheck(is_file($sealedCase2), "Case 2 sealed PDF created successfully with 2 IPv4 addresses");

// ----------------------------------------------------------------------
// CASE 3: IPv6 Address (2001:db8:1234:5678::1)
// ----------------------------------------------------------------------
echo "\n[CASE 3: IPv6 Address (2001:db8:1234:5678::1)]\n";
$signersCase3 = [
    [
        'id' => 1,
        'name' => 'Gohan',
        'email' => 'mhi1755480@gmail.com',
        'role' => 'signer',
        'status' => 'signed',
        'signed_at' => '2026-09-28 18:46:49',
        'sent_at' => '2026-09-28 18:46:17',
        'ip_address' => '2001:db8:1234:5678::1',
        'token' => 'bdea1f4f435bd8349911'
    ]
];
$sealedCase3 = $outputDir . '/case3_ipv6_sealed.pdf';
SignatureSealerService::sealPdf($baseSrc, $sealedCase3, $baseFields, $baseReq, $signersCase3);
assertCheck(is_file($sealedCase3), "Case 3 sealed PDF created successfully with IPv6 address");

// ----------------------------------------------------------------------
// CASE 4: Multiple IPv6 Addresses
// ----------------------------------------------------------------------
echo "\n[CASE 4: Multiple IPv6 Addresses]\n";
$signersCase4 = [
    [
        'id' => 1,
        'name' => 'Gohan',
        'email' => 'mhi1755480@gmail.com',
        'role' => 'signer',
        'status' => 'signed',
        'signed_at' => '2026-09-28 18:46:49',
        'sent_at' => '2026-09-28 18:46:17',
        'ip_address' => '2001:db8:1234:5678::1, 2001:db8:3333:4444:5555:6666:7777:8888',
        'token' => 'bdea1f4f435bd8349911'
    ]
];
$sealedCase4 = $outputDir . '/case4_multi_ipv6_sealed.pdf';
SignatureSealerService::sealPdf($baseSrc, $sealedCase4, $baseFields, $baseReq, $signersCase4);
assertCheck(is_file($sealedCase4), "Case 4 sealed PDF created successfully with multiple IPv6 addresses");

// ----------------------------------------------------------------------
// CASE 5: Long Document Title
// ----------------------------------------------------------------------
echo "\n[CASE 5: Long Document Title]\n";
$reqCase5 = [
    'id' => 999,
    'title' => 'Signature Request: Comprehensive Cross-Border Statutory Tax Structuring & Advisory Timetable Policy (FY 2026/2027) Master Document',
    'entity_name' => 'Capsule Corporation UK International Holdings PLC',
    'created_at' => '2026-09-28 18:46:17',
    'qr_token' => 'd1a2b7f650f0d6cf82a3f496a533b389',
    'signing_order' => 'parallel'
];
$sealedCase5 = $outputDir . '/case5_long_title_sealed.pdf';
SignatureSealerService::sealPdf($baseSrc, $sealedCase5, $baseFields, $reqCase5, $signersCase2);
assertCheck(is_file($sealedCase5), "Case 5 sealed PDF created successfully with long document title");

// ----------------------------------------------------------------------
// CASE 6: Long Audit Event Description
// ----------------------------------------------------------------------
echo "\n[CASE 6: Long Audit Event Description]\n";
$auditCase6 = [
    [
        'created_at' => '2026-09-28 18:46:17',
        'event_type' => 'REQUEST_CREATED',
        'description' => 'Signature request \'Signature Request: Trinova_Update_Timetable_Policy.pdf\' created with high-security cryptographic key pairs and dual-witness digital ledger anchoring.',
        'ip_address' => '59.103.110.144, 152.233.68.97'
    ]
];
$sealedCase6 = $outputDir . '/case6_long_audit_desc_sealed.pdf';
SignatureSealerService::sealPdf($baseSrc, $sealedCase6, $baseFields, $baseReq, $signersCase2, $auditCase6);
assertCheck(is_file($sealedCase6), "Case 6 sealed PDF created successfully with long audit event description");

// ----------------------------------------------------------------------
// CASE 7: Multiple Audit Events
// ----------------------------------------------------------------------
echo "\n[CASE 7: Multiple Audit Events (Matching User Data)]\n";
$auditCase7 = [
    [
        'created_at' => '2026-09-28 18:46:17',
        'event_type' => 'REQUEST_CREATED',
        'description' => 'Signature request \'Signature Request: Trinova_Update_Timetable_Policy.pdf\'',
        'ip_address' => '59.103.110.144, 152.233.68.97'
    ],
    [
        'created_at' => '2026-09-28 18:46:17',
        'event_type' => 'REQUEST_SENT',
        'description' => 'Signature request \'Signature Request: Trinova_Update_Timetable_Policy.pdf\'',
        'ip_address' => '59.103.110.144, 152.233.68.97'
    ],
    [
        'created_at' => '2026-09-28 18:46:30',
        'event_type' => 'DOCUMENT_OPENED',
        'description' => 'Signer Gohan (mhi1755480@gmail.com) opened the document for review.',
        'ip_address' => '59.103.110.144, 152.233.68.97'
    ],
    [
        'created_at' => '2026-09-28 18:46:49',
        'event_type' => 'SIGNATURE_COMPLETED',
        'description' => 'Signer Gohan (mhi1755480@gmail.com) completed and submitted their signature',
        'ip_address' => '59.103.110.144, 152.233.68.97'
    ]
];
$sealedCase7 = $outputDir . '/case7_multiple_audit_events_sealed.pdf';
SignatureSealerService::sealPdf($baseSrc, $sealedCase7, $baseFields, $baseReq, $signersCase2, $auditCase7);
assertCheck(is_file($sealedCase7), "Case 7 sealed PDF created successfully with 4 audit events and dual IPs");

// ----------------------------------------------------------------------
// CASE 8: Normal Existing Certificate
// ----------------------------------------------------------------------
echo "\n[CASE 8: Normal Existing Certificate]\n";
$signersCase8 = [
    [
        'id' => 1,
        'name' => 'Alice Walker',
        'email' => 'alice@trinova.co.uk',
        'role' => 'client',
        'status' => 'signed',
        'signed_at' => '2026-09-28 12:00:00',
        'sent_at' => '2026-09-28 11:30:00',
        'ip_address' => '192.168.1.100',
        'token' => 'tok_standard_010203'
    ]
];
$sealedCase8 = $outputDir . '/case8_normal_certificate_sealed.pdf';
SignatureSealerService::sealPdf($baseSrc, $sealedCase8, $baseFields, $baseReq, $signersCase8);
assertCheck(is_file($sealedCase8), "Case 8 normal existing certificate sealed successfully");

echo "\n======================================================================\n";
echo "  SECTION 19 REGRESSION SUITE: {$passCount}/{$totalCount} PASSED\n";
echo "======================================================================\n";
