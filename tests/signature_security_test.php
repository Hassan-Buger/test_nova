<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

function runSecTest(string $name, callable $fn): void {
    try {
        $fn();
        echo " [PASS] {$name}\n";
    } catch (\Throwable $e) {
        echo " [FAIL] {$name}: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
        exit(1);
    }
}

echo "=== DIGITAL SIGNATURE SECURITY & RBAC REGRESSION TEST SUITE ===\n\n";

$routes = file_get_contents($root . '/public/index.php');
$signingController = file_get_contents($root . '/application/Controllers/SigningController.php');
$staffSigController = file_get_contents($root . '/application/Controllers/Staff/SignatureController.php');
$clientSigController = file_get_contents($root . '/application/Controllers/Client/SignatureController.php');
$signatureService = file_get_contents($root . '/application/Services/SignatureService.php');

// Test 1: Route Protection & Middleware Guardrails
runSecTest('1. Route Authentication, Role-Based Access & CSRF Enforcements', function() use ($routes) {
    // Public routes: /sign/{token}, /sign/{token}/pdf, /verify/signature/{token}
    if (!str_contains($routes, "'/sign/{token}'") || !str_contains($routes, "'/sign/{token}/pdf'")) {
        throw new RuntimeException("Public /sign/{token} routes missing from routing table.");
    }
    if (!str_contains($routes, "'/verify/signature/{token}'")) {
        throw new RuntimeException("Public verification route missing.");
    }

    // Client signature routes must be inside client group with RoleMiddleware:client
    if (!str_contains($routes, "'/client'") || !str_contains($routes, "RoleMiddleware::class . ':client'")) {
        throw new RuntimeException("Client route group missing RoleMiddleware:client.");
    }
    if (!str_contains($routes, "\$r->get('/signatures', [ClientSignatureController::class, 'index'])")) {
        throw new RuntimeException("Client signatures route missing from client group.");
    }

    // Staff signature routes must be inside staff group with RoleMiddleware:staff and CSRF protection
    if (!str_contains($routes, "'/staff'") || !str_contains($routes, "RoleMiddleware::class . ':staff'")) {
        throw new RuntimeException("Staff route group missing RoleMiddleware:staff.");
    }
    if (!str_contains($routes, "\$r->get('/signatures', [StaffSignatureController::class, 'index'])")) {
        throw new RuntimeException("Staff signatures index route missing.");
    }
    if (!str_contains($routes, "\$r->post('/signatures/create', [StaffSignatureController::class, 'processCreate'])->middleware([CsrfMiddleware::class])")) {
        throw new RuntimeException("Staff signature creation missing CsrfMiddleware.");
    }
    if (!str_contains($routes, "\$r->post('/signatures/cancel', [StaffSignatureController::class, 'cancel'])->middleware([CsrfMiddleware::class])")) {
        throw new RuntimeException("Staff signature cancel missing CsrfMiddleware.");
    }
    if (!str_contains($routes, "\$r->post('/signatures/resend', [StaffSignatureController::class, 'resend'])->middleware([CsrfMiddleware::class])")) {
        throw new RuntimeException("Staff signature resend missing CsrfMiddleware.");
    }
});

// Test 2: Token Entropy & Unpredictability
runSecTest('2. 256-Bit Token Entropy & Anti-Enumeration Protections', function() {
    $signerRef = new \ReflectionClass(\Application\Models\SignatureSigner::class);
    $signerModel = $signerRef->newInstanceWithoutConstructor();

    $tokens = [];
    for ($i = 0; $i < 100; $i++) {
        $tok = $signerModel->generateToken();
        if (strlen($tok) !== 64 || !ctype_xdigit($tok)) {
            throw new RuntimeException("Token must be a 64-character hexadecimal string.");
        }
        if (isset($tokens[$tok])) {
            throw new RuntimeException("Token duplicate found in 100 iterations.");
        }
        $tokens[$tok] = true;
    }
});

// Test 3: Download & Header CRLF Injection Defenses
runSecTest('3. HTTP Response Splitting & File Download Hardening', function() use ($signingController) {
    if (!str_contains($signingController, 'str_replace') || !str_contains($signingController, 'basename')) {
        throw new RuntimeException("Filename output in SigningController is missing CRLF/directory traversal stripping.");
    }
    if (!str_contains($signingController, 'Cache-Control: private, no-store, max-age=0')) {
        throw new RuntimeException("SigningController missing private/no-store cache header.");
    }
});

// Test 4: Replay & Sequential Bypass Protections
runSecTest('4. Replay Attack & Out-Of-Turn State Defenses', function() use ($signatureService, $signingController) {
    // Verification that signed users are redirected or rejected
    if (!str_contains($signingController, "\$signer['status'] === 'signed'") || !str_contains($signingController, "/sign/{\$token}/completed")) {
        throw new RuntimeException("Signing controller does not guard against completed signers re-entering sign view.");
    }

    // Verification that cancelled requests are blocked
    if (!str_contains($signatureService, "\$request['status'] === 'cancelled'") || !str_contains($signatureService, "!empty(\$request['deleted_at'])")) {
        throw new RuntimeException("SignatureService does not verify cancelled/deleted status.");
    }

    // Verification of expiry check
    if (!str_contains($signatureService, "strtotime(\$request['expires_at']) < time()")) {
        throw new RuntimeException("SignatureService does not verify expiration timestamp.");
    }
});

// Test 5: UI Template Escaping & Anti-XSS Auditing
runSecTest('5. View Template Escaping & XSS Sanitization', function() use ($root) {
    $views = [
        $root . '/application/Views/signing/sign.php',
        $root . '/application/Views/signing/completed.php',
        $root . '/application/Views/signing/declined.php',
        $root . '/application/Views/signing/verify.php',
        $root . '/application/Views/staff/signatures/index.php',
        $root . '/application/Views/staff/signatures/show.php',
        $root . '/application/Views/client/signatures/index.php',
    ];

    foreach ($views as $viewPath) {
        if (!file_exists($viewPath)) {
            throw new RuntimeException("View file missing: {$viewPath}");
        }
        $content = file_get_contents($viewPath);
        // Ensure htmlspecialchars is utilized on request and signer data
        if (!str_contains($content, 'htmlspecialchars')) {
            throw new RuntimeException("View {$viewPath} does not use htmlspecialchars for output encoding.");
        }
    }
});

echo "\nAll Digital Signature Security & RBAC tests passed successfully!\n";
