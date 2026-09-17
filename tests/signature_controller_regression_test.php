<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

use Application\Models\Client;
use Application\Models\SignatureRequest;
use Application\Controllers\Staff\SignatureController as StaffSignatureController;

echo "=== SIGNATURE CONTROLLER & MODEL COLUMN REGRESSION TEST ===\n\n";

// 1. Verify Client has getAllWithDetails and getAllWithUsers
$clientRef = new ReflectionClass(Client::class);
if (!$clientRef->hasMethod('getAllWithUsers')) {
    throw new RuntimeException("Client model missing getAllWithUsers()");
}
if (!$clientRef->hasMethod('getAllWithDetails')) {
    throw new RuntimeException("Client model missing getAllWithDetails()");
}
echo " [PASS] 1. Client model methods getAllWithUsers() and getAllWithDetails() verified\n";

// 2. Verify SignatureRequest SQL does not reference invalid columns (c.name, c.company_name)
$sigReqFile = file_get_contents($root . '/application/Models/SignatureRequest.php');
if (preg_match('/c\.name\b/', $sigReqFile)) {
    throw new RuntimeException("SignatureRequest.php still contains invalid column reference 'c.name'");
}
if (preg_match('/c\.company_name\b/', $sigReqFile)) {
    throw new RuntimeException("SignatureRequest.php still contains invalid column reference 'c.company_name'");
}
echo " [PASS] 2. SignatureRequest SQL column references verified (no c.name or c.company_name)\n";

// 3. Verify Staff SignatureController does not call undefined methods on Client
$staffSigFile = file_get_contents($root . '/application/Controllers/Staff/SignatureController.php');
if (str_contains($staffSigFile, 'new Client())->getAllWithDetails()')) {
    throw new RuntimeException("Staff SignatureController.php should use canonical (new Client())->getAllWithUsers()");
}
echo " [PASS] 3. Staff SignatureController canonical method calls verified\n";

// 4. Verify audit event description view key compatibility in templates
$auditShowFile = file_get_contents($root . '/application/Views/staff/signatures/show.php');
if (!str_contains($auditShowFile, "event_description")) {
    throw new RuntimeException("show.php must reference event_description for audit events");
}
$auditVerifyFile = file_get_contents($root . '/application/Views/signing/verify.php');
if (!str_contains($auditVerifyFile, "event_description")) {
    throw new RuntimeException("verify.php must reference event_description for audit events");
}
echo " [PASS] 4. Audit event template view compatibility verified in show.php and verify.php\n\n";

echo "All Signature Controller & Model regression tests passed successfully!\n";
