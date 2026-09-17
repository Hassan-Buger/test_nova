<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

use Application\Models\SignatureField;
use Application\Models\SignatureRequest;
use Application\Models\SignatureSigner;
use Application\Services\SignatureService;

function runServiceTest(string $name, callable $fn): void {
    try {
        $fn();
        echo " [PASS] {$name}\n";
    } catch (\Throwable $e) {
        echo " [FAIL] {$name}: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
        exit(1);
    }
}

echo "=== DIGITAL SIGNATURE SERVICE & LOGIC TEST SUITE ===\n\n";

// Test 1: Cryptographic Token Security
runServiceTest('1. Token Cryptographic Entropy & Format Verification', function() {
    $signerRef = new \ReflectionClass(SignatureSigner::class);
    $signerModel = $signerRef->newInstanceWithoutConstructor();
    $token1 = $signerModel->generateToken();
    $token2 = $signerModel->generateToken();

    if (strlen($token1) !== 64 || !ctype_xdigit($token1)) {
        throw new RuntimeException("Signer token 1 must be exactly 64 hexadecimal characters, got: {$token1}");
    }

    if (strlen($token2) !== 64 || !ctype_xdigit($token2)) {
        throw new RuntimeException("Signer token 2 must be exactly 64 hexadecimal characters, got: {$token2}");
    }

    if ($token1 === $token2) {
        throw new RuntimeException("Cryptographic collision detected: two consecutively generated tokens are identical!");
    }

    $reqRef = new \ReflectionClass(SignatureRequest::class);
    $reqModel = $reqRef->newInstanceWithoutConstructor();
    $qrToken = $reqModel->generateQrToken();
    if (strlen($qrToken) < 32 || !ctype_xdigit($qrToken)) {
        throw new RuntimeException("QR audit verification token must be 32+ hexadecimal characters, got: {$qrToken}");
    }
});

// Test 2: Sequential vs Parallel Turn Logic
runServiceTest('2. Workflow Turn Determination (Parallel vs Sequential)', function() {
    $parallelRequest = [
        'id'            => 901,
        'signing_order' => 'parallel',
        'status'        => 'pending',
    ];

    $signersParallel = [
        ['id' => 1, 'request_id' => 901, 'signing_order' => 1, 'status' => 'pending'],
        ['id' => 2, 'request_id' => 901, 'signing_order' => 2, 'status' => 'pending'],
    ];

    // In parallel mode, all signers can sign immediately
    $reflection = new \ReflectionClass(SignatureService::class);
    $method = $reflection->getMethod('isSignerTurn');

    $turn1 = $method->invoke(null, $signersParallel[0], $parallelRequest, $signersParallel);
    $turn2 = $method->invoke(null, $signersParallel[1], $parallelRequest, $signersParallel);

    if ($turn1 !== true || $turn2 !== true) {
        throw new RuntimeException("In parallel mode, all signers must have active signing turn.");
    }

    // In sequential mode, signer 2 must wait for signer 1
    $sequentialRequest = [
        'id'            => 902,
        'signing_order' => 'sequential',
        'status'        => 'pending',
    ];

    $signersSequential = [
        ['id' => 10, 'request_id' => 902, 'signing_order' => 1, 'status' => 'pending'],
        ['id' => 20, 'request_id' => 902, 'signing_order' => 2, 'status' => 'pending'],
    ];

    $turnSeq1 = $method->invoke(null, $signersSequential[0], $sequentialRequest, $signersSequential);
    $turnSeq2 = $method->invoke(null, $signersSequential[1], $sequentialRequest, $signersSequential);

    if ($turnSeq1 !== true) {
        throw new RuntimeException("In sequential mode, Signer 1 (order 1) must be allowed to sign.");
    }
    if ($turnSeq2 !== false) {
        throw new RuntimeException("In sequential mode, Signer 2 (order 2) must be blocked while Signer 1 is pending.");
    }

    // Once signer 1 signs, signer 2 turn becomes active
    $signersSequential[0]['status'] = 'signed';
    $turnSeq2After = $method->invoke(null, $signersSequential[1], $sequentialRequest, $signersSequential);

    if ($turnSeq2After !== true) {
        throw new RuntimeException("In sequential mode, Signer 2 must be allowed to sign after Signer 1 completes.");
    }
});

// Test 3: Structural & Schema Contract Alignment
runServiceTest('3. Schema & Migration Contract Verification', function() use ($root) {
    $databaseSql = file_get_contents($root . '/config/database.sql');
    $migrationSql = file_get_contents($root . '/config/digital_signatures_migration.sql');
    $schemaMigrator = file_get_contents($root . '/application/Core/SchemaMigrator.php');
    $schemaGuard = file_get_contents($root . '/application/Services/SchemaGuard.php');

    $requiredTables = [
        'signature_requests',
        'signature_signers',
        'signature_fields',
        'signature_audit_events',
    ];

    foreach ($requiredTables as $tbl) {
        if (!str_contains($databaseSql, "`{$tbl}`")) {
            throw new RuntimeException("database.sql missing definition for `{$tbl}`.");
        }
        if (!str_contains($migrationSql, "`{$tbl}`")) {
            throw new RuntimeException("digital_signatures_migration.sql missing definition for `{$tbl}`.");
        }
        if (!str_contains($schemaMigrator, "`{$tbl}`") && !str_contains($schemaMigrator, "'{$tbl}'")) {
            throw new RuntimeException("SchemaMigrator.php missing migration reference for `{$tbl}`.");
        }
    }

    // Verify SchemaGuard assertion method
    if (!str_contains($schemaGuard, 'function assertSignaturesReady')) {
        throw new RuntimeException("SchemaGuard missing assertSignaturesReady() check.");
    }

    // Verify critical columns in signature_requests
    foreach (['original_checksum_sha256', 'signed_checksum_sha256', 'qr_token', 'signing_order', 'signed_document_id'] as $col) {
        if (!str_contains($databaseSql, "`{$col}`")) {
            throw new RuntimeException("database.sql missing column signature_requests.{$col}");
        }
    }

    // Verify token columns in signature_signers
    foreach (['token', 'signing_order', 'ip_address', 'user_agent', 'signed_at', 'decline_reason'] as $col) {
        if (!str_contains($databaseSql, "`{$col}`")) {
            throw new RuntimeException("database.sql missing column signature_signers.{$col}");
        }
    }
});

// Test 4: Model Methods & Field Mapping Contract
runServiceTest('4. Model Signatures & Autoloading Verification', function() {
    $models = [
        \Application\Models\SignatureRequest::class,
        \Application\Models\SignatureSigner::class,
        \Application\Models\SignatureField::class,
        \Application\Models\SignatureAuditEvent::class,
    ];

    foreach ($models as $m) {
        $ref = new \ReflectionClass($m);
        if (!$ref->isInstantiable()) {
            throw new RuntimeException("Model {$m} is not instantiable.");
        }
    }

    // Verify SignatureField methods
    $fieldRef = new \ReflectionClass(\Application\Models\SignatureField::class);
    foreach (['find', 'getByRequestId', 'getBySignerId', 'create', 'saveFieldValue', 'clearFieldValue', 'hasUnsignedRequiredFields'] as $method) {
        if (!$fieldRef->hasMethod($method)) {
            throw new RuntimeException("SignatureField missing required method: {$method}");
        }
    }
});

echo "\nAll Digital Signature Service & Logic tests passed successfully!\n";

