<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

use Application\Services\FileStorageService;
use setasign\Fpdi\Fpdi;

echo "=== FILE STORAGE ARTIFACT RESILIENCE & SELF-HEALING TEST ===\n\n";

// Test 1: Self-healing missing document file
$testFilename = 'temp_missing_test_' . bin2hex(random_bytes(6)) . '.pdf';
$storageDir = \Application\Config\App::get('storage_dir') ?: ($root . '/storage');
$testFullPath = $storageDir . '/uploads/' . $testFilename;

// Ensure it does not exist before test
if (is_file($testFullPath)) {
    @unlink($testFullPath);
}

$resolved = FileStorageService::resolvePath($testFilename, 'Test Engagement Letter 2026.pdf');

if (!is_file($resolved)) {
    throw new RuntimeException("resolvePath failed to generate physical file at {$resolved}");
}
if (filesize($resolved) < 500) {
    throw new RuntimeException("Generated PDF file is suspiciously small: " . filesize($resolved) . " bytes");
}
echo " [PASS] 1. Dynamic artifact generation for missing storage path verified (" . filesize($resolved) . " bytes)\n";

// Test 2: Verify generated PDF is 100% valid and parseable by FPDI
$fpdi = new Fpdi();
$pageCount = $fpdi->setSourceFile($resolved);
if ($pageCount < 1) {
    throw new RuntimeException("Generated PDF has invalid page count: {$pageCount}");
}
$tpl = $fpdi->importPage(1);
$fpdi->AddPage();
$fpdi->useTemplate($tpl);
echo " [PASS] 2. Generated PDF structure validated by FPDI engine ({$pageCount} page(s))\n";

// Test 3: Seed artifacts presence
$seedAccounts = $storageDir . '/seeds/draft_accounts_2025_hash.pdf';
if (!is_file($seedAccounts)) {
    throw new RuntimeException("storage/seeds/draft_accounts_2025_hash.pdf missing");
}
$seedVat = $storageDir . '/seeds/vat_return_q2_hash.pdf';
if (!is_file($seedVat)) {
    throw new RuntimeException("storage/seeds/vat_return_q2_hash.pdf missing");
}
echo " [PASS] 3. Seed template artifacts verified in storage/seeds\n\n";

// Clean up temporary test file
@unlink($testFullPath);

echo "All Storage Resilience & Self-Healing tests passed successfully!\n";
