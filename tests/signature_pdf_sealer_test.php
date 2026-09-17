<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

use Application\Services\SignatureSealerService;
use setasign\Fpdi\Fpdi;

function runPdfTest(string $name, callable $fn): void {
    try {
        $fn();
        echo " [PASS] {$name}\n";
    } catch (\Throwable $e) {
        echo " [FAIL] {$name}: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
        exit(1);
    }
}

echo "=== DIGITAL SIGNATURE PDF SEALER & CERTIFICATE TEST SUITE ===\n\n";

// Ensure test scratch directory exists
$scratchDir = $root . '/storage/uploads/test_artifacts';
if (!is_dir($scratchDir)) {
    mkdir($scratchDir, 0755, true);
}

// Helper: Generate a 2-page base sample contract PDF using FPDF
$originalPdfPath = $scratchDir . '/sample_contract.pdf';
$fpdf = new \FPDF();
$fpdf->AddPage();
$fpdf->SetFont('Helvetica', 'B', 16);
$fpdf->Cell(0, 10, 'TRINOVA ACCOUNTING CLIENT SERVICES AGREEMENT', 0, 1, 'C');
$fpdf->SetFont('Helvetica', '', 11);
$fpdf->Ln(5);
$fpdf->MultiCell(0, 6, "This Agreement is entered into between TriNova Accounting and the Client.\n\nClause 1: Scope of Engagement\nTriNova Accounting agrees to provide statutory compliance, tax return preparation, and annual confirmation filing services.\n\nClause 2: Execution and Digital Signatures\nThe parties agree that execution via TriNova's digital signature platform shall have the full legal force and effect of an original manual signature.");

// Page 2
$fpdf->AddPage();
$fpdf->SetFont('Helvetica', 'B', 14);
$fpdf->Cell(0, 10, 'SCHEDULE A - EXECUTION PAGE', 0, 1, 'L');
$fpdf->SetFont('Helvetica', '', 10);
$fpdf->MultiCell(0, 5, "IN WITNESS WHEREOF, the parties hereto have executed this Agreement on the dates set forth below.\n\nSignatory Section:");
$fpdf->Output('F', $originalPdfPath);

// Generate a dummy signature PNG image data URI
$sigImg = imagecreatetruecolor(300, 100);
$bg = imagecolorallocatealpha($sigImg, 255, 255, 255, 127);
imagefill($sigImg, 0, 0, $bg);
imagesavealpha($sigImg, true);
$textColor = imagecolorallocate($sigImg, 15, 23, 42);
imagestring($sigImg, 5, 20, 40, 'Jane Doe (Signed)', $textColor);
ob_start();
imagepng($sigImg);
$sigPngData = ob_get_clean();
imagedestroy($sigImg);
$signatureDataUri = 'data:image/png;base64,' . base64_encode($sigPngData);

// Test 1: Validate original PDF creation and initial page count
runPdfTest('1. Base Multi-Page PDF Verification', function() use ($originalPdfPath) {
    if (!file_exists($originalPdfPath)) {
        throw new RuntimeException("Original test PDF was not generated.");
    }
    $fpdi = new Fpdi();
    $pageCount = $fpdi->setSourceFile($originalPdfPath);
    if ($pageCount !== 2) {
        throw new RuntimeException("Expected 2 pages in original PDF, found: {$pageCount}");
    }
});

// Test 2: Execute Sealing & Certificate of Completion stamping
$signedPdfRelativePath = 'test_artifacts/sample_contract_signed.pdf';
$signedPdfFullPath = $root . '/storage/uploads/' . $signedPdfRelativePath;
if (file_exists($signedPdfFullPath)) {
    unlink($signedPdfFullPath);
}

runPdfTest('2. FPDI Stamping, Coordinate Transformation & Certificate Sealing', function() use (
    $originalPdfPath, $signedPdfFullPath, $signatureDataUri
) {
    $request = [
        'id'            => 101,
        'title'         => 'TriNova Client Services Agreement',
        'signing_order' => 'parallel',
        'created_at'    => '2026-09-17 10:00:00',
    ];

    $signers = [
        [
            'id'            => 501,
            'name'          => 'Jane Doe',
            'email'         => 'jane.doe@clientcorp.co.uk',
            'role'          => 'signer',
            'status'        => 'signed',
            'signed_at'     => '2026-09-17 14:32:10',
            'ip_address'    => '192.168.1.105',
            'user_agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'signing_order' => 1,
        ]
    ];

    $fields = [
        [
            'signer_id'   => 501,
            'field_type'  => 'signature',
            'page_number' => 2,
            'position_x'  => 15.0, // 15% from left
            'position_y'  => 60.0, // 60% from top
            'width'       => 35.0, // 35% width
            'height'      => 10.0, // 10% height
            'field_value' => $signatureDataUri,
        ],
        [
            'signer_id'   => 501,
            'field_type'  => 'date',
            'page_number' => 2,
            'position_x'  => 55.0,
            'position_y'  => 60.0,
            'width'       => 25.0,
            'height'      => 5.0,
            'field_value' => '2026-09-17',
        ]
    ];

    $auditEvents = [
        [
            'event_type'  => 'created',
            'description' => 'Signature request created by TriNova Staff',
            'ip_address'  => '127.0.0.1',
            'created_at'  => '2026-09-17 10:00:00',
        ],
        [
            'event_type'  => 'viewed',
            'description' => 'Document viewed by Jane Doe',
            'ip_address'  => '192.168.1.105',
            'created_at'  => '2026-09-17 14:30:15',
        ],
        [
            'event_type'  => 'signed',
            'description' => 'Document signed by Jane Doe',
            'ip_address'  => '192.168.1.105',
            'created_at'  => '2026-09-17 14:32:10',
        ],
    ];

    $qrToken = 'test_cert_token_abc123';

    // Execute stamping engine
    $result = SignatureSealerService::sealPdf(
        $originalPdfPath,
        $signedPdfFullPath,
        $fields,
        $request,
        $signers,
        $auditEvents,
        $qrToken
    );

    if (!file_exists($signedPdfFullPath)) {
        throw new RuntimeException("Signed PDF was not created at {$signedPdfFullPath}");
    }

    if (empty($result['original_checksum']) || strlen($result['original_checksum']) !== 64) {
        throw new RuntimeException("Invalid original checksum: " . ($result['original_checksum'] ?? ''));
    }

    if (empty($result['signed_checksum']) || strlen($result['signed_checksum']) !== 64) {
        throw new RuntimeException("Invalid signed checksum: " . ($result['signed_checksum'] ?? ''));
    }

    if ($result['original_checksum'] === $result['signed_checksum']) {
        throw new RuntimeException("Original checksum and signed checksum should not be identical.");
    }
});

// Test 3: Validate Page Count and Certificate Appended
runPdfTest('3. Verify Page Count Increment (+1 Certificate Page)', function() use ($signedPdfFullPath) {
    $fpdi = new Fpdi();
    $pageCount = $fpdi->setSourceFile($signedPdfFullPath);
    if ($pageCount !== 3) {
        throw new RuntimeException("Expected 3 pages in sealed PDF (2 original + 1 Certificate), found: {$pageCount}");
    }
});

// Test 4: Checksum Integrity Verification
runPdfTest('4. Cryptographic SHA-256 Tamper-Evidence', function() use ($originalPdfPath, $signedPdfFullPath) {
    $computedOriginalSha = hash_file('sha256', $originalPdfPath);
    $computedSignedSha = hash_file('sha256', $signedPdfFullPath);

    if (strlen($computedOriginalSha) !== 64 || strlen($computedSignedSha) !== 64) {
        throw new RuntimeException("SHA-256 calculation failed.");
    }

    // Verify modifying the file alters hash
    $tampered = $signedPdfFullPath . '.tampered';
    copy($signedPdfFullPath, $tampered);
    file_put_contents($tampered, "MALICIOUS BYTE", FILE_APPEND);
    $tamperedSha = hash_file('sha256', $tampered);
    unlink($tampered);

    if ($tamperedSha === $computedSignedSha) {
        throw new RuntimeException("Tampered file hash matched! SHA-256 verification failed.");
    }
});

echo "\nAll Digital Signature PDF Sealer & Certificate tests passed successfully!\n";
