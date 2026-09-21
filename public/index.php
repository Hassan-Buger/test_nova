<?php

// Production-safe bootstrap: diagnostics are logged, never rendered into a
// user response before the application exception handler is available.
ini_set('display_errors','0');
ini_set('display_startup_errors','0');
ini_set('log_errors','1');

/**
 * TriNova Client Portal - Front Controller
 */

$appDir = dirname(__DIR__);
$autoloadFound = false;

foreach ([$appDir . '/vendor/autoload.php', $appDir . '/8493files/vendor/autoload.php', $appDir . '/trinova_app/vendor/autoload.php'] as $autoloadFile) {
    if (file_exists($autoloadFile)) {
        require_once $autoloadFile;
        $basePath = dirname($autoloadFile, 2);
        $autoloadFound = true;
        break;
    }
}

if (!$autoloadFound) {
    $basePath = $appDir;
    // Built-in PSR-4 autoloader fallback for Application\ namespace
    spl_autoload_register(function ($class) use ($appDir) {
        $prefix = 'Application\\';
        $baseDir = $appDir . '/application/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });
}

use Application\Core\Application;
use Application\Middleware\AuthMiddleware;
use Application\Middleware\CsrfMiddleware;
use Application\Middleware\RoleMiddleware;
use Application\Middleware\SessionTimeoutMiddleware;
use Application\Controllers\Auth\AuthController;
use Application\Controllers\Auth\PasswordResetController;
use Application\Controllers\Auth\ActivationController;
use Application\Controllers\Client\DashboardController as ClientDashboardController;
use Application\Controllers\Client\DocumentController as ClientDocumentController;
use Application\Controllers\Client\MessageController as ClientMessageController;
use Application\Controllers\Client\RequestController as ClientRequestController;
use Application\Controllers\Client\DeadlineController as ClientDeadlineController;
use Application\Controllers\Client\MeetingController as ClientMeetingController;
use Application\Controllers\Client\ProfileController as ClientProfileController;
use Application\Controllers\Staff\DashboardController as StaffDashboardController;
use Application\Controllers\Staff\ClientController as StaffClientController;
use Application\Controllers\Staff\ClientCsvController as StaffClientCsvController;
use Application\Controllers\Staff\DirectorCsvController as StaffDirectorCsvController;
use Application\Controllers\Staff\DocumentController as StaffDocumentController;
use Application\Controllers\Staff\RequestController as StaffRequestController;
use Application\Controllers\Staff\MessageController as StaffMessageController;
use Application\Controllers\Staff\DeadlineController as StaffDeadlineController;
use Application\Controllers\Staff\UserAdminController as StaffUserAdminController;
use Application\Controllers\Staff\AuditController as StaffAuditController;
use Application\Controllers\Staff\TrashController as StaffTrashController;
use Application\Controllers\Staff\SignatureController as StaffSignatureController;
use Application\Controllers\Client\SignatureController as ClientSignatureController;
use Application\Controllers\SigningController;
use Application\Controllers\NotificationController;
use Application\Middleware\ApiAuthMiddleware;
use Application\Controllers\Api\HealthController as ApiHealthController;
use Application\Controllers\Api\ClientApiController as ApiClientController;
use Application\Controllers\Api\StatutoryApiController as ApiStatutoryController;
use Application\Controllers\Api\NoteApiController as ApiNoteController;

// Simple dotenv loader fallback for environment variables
$envFile = $basePath . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (strpos($trimmed, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$key, $val] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim(trim($val), '"\'');
        }
    }
}

$app = new Application($basePath);

// --- PUBLIC AUTH ROUTES ---
$app->router->get('/', function($req, $res) {
    $res->redirect('/login');
});
$app->router->get('/login', [AuthController::class, 'showLogin']);
$app->router->post('/login', [AuthController::class, 'login'])->middleware([CsrfMiddleware::class]);
$app->router->post('/logout', [AuthController::class, 'logout'])->middleware([AuthMiddleware::class, CsrfMiddleware::class]);

$app->router->get('/password/reset', [PasswordResetController::class, 'showRequestForm']);
$app->router->post('/password/reset', [PasswordResetController::class, 'sendResetLink'])->middleware([CsrfMiddleware::class]);
$app->router->get('/password/verify', [PasswordResetController::class, 'showVerifyForm']);
$app->router->post('/password/verify', [PasswordResetController::class, 'processCodeVerify'])->middleware([CsrfMiddleware::class]);

$app->router->get('/activate', [ActivationController::class, 'showActivationForm']);
$app->router->post('/activate', [ActivationController::class, 'processActivation'])->middleware([CsrfMiddleware::class]);

// --- SHARED DOWNLOAD ROUTE (accessible by all authenticated users: staff & clients) ---
$app->router->get('/documents/download/{id}', [ClientDocumentController::class, 'download'])->middleware([AuthMiddleware::class, SessionTimeoutMiddleware::class]);
$app->router->get('/documents/view/{id}', [ClientDocumentController::class, 'view'])->middleware([AuthMiddleware::class, SessionTimeoutMiddleware::class]);
$app->router->get('/documents/availability/{id}', [ClientDocumentController::class, 'availability'])->middleware([AuthMiddleware::class, SessionTimeoutMiddleware::class]);
$app->router->get('/notifications/feed', [NotificationController::class, 'feed'])->middleware([AuthMiddleware::class, SessionTimeoutMiddleware::class]);
$app->router->post('/notifications/read-all', [NotificationController::class, 'readAll'])->middleware([AuthMiddleware::class, SessionTimeoutMiddleware::class, CsrfMiddleware::class]);
$app->router->get('/api/notifications', [NotificationController::class, 'feed'])->middleware([AuthMiddleware::class, SessionTimeoutMiddleware::class]);
$app->router->post('/api/notifications/mark-as-read', [NotificationController::class, 'read'])->middleware([AuthMiddleware::class, SessionTimeoutMiddleware::class, CsrfMiddleware::class]);

// --- DIGITAL SIGNATURE & VERIFICATION ROUTES (accessible by token) ---
$app->router->get('/sign/{token}', [SigningController::class, 'showSign']);
$app->router->get('/sign/{token}/pdf', [SigningController::class, 'streamPdf']);
$app->router->post('/sign/{token}/submit', [SigningController::class, 'submitSign']);
$app->router->post('/sign/{token}/decline', [SigningController::class, 'decline']);
$app->router->get('/sign/{token}/completed', [SigningController::class, 'showCompleted']);
$app->router->get('/sign/{token}/download', [SigningController::class, 'download']);
$app->router->get('/verify/signature/{token}', [SigningController::class, 'verify']);

// --- SECURED CLIENT ROUTES ---
$app->router->group([
    'prefix' => '/client',
    'middleware' => [AuthMiddleware::class, SessionTimeoutMiddleware::class, RoleMiddleware::class . ':client']
], function($r) {
    $r->get('/dashboard', [ClientDashboardController::class, 'index']);
    $r->get('/signatures', [ClientSignatureController::class, 'index']);
    $r->get('/documents/upload', [ClientDocumentController::class, 'showUpload']);
    $r->post('/documents/upload', [ClientDocumentController::class, 'processUpload'])->middleware([CsrfMiddleware::class]);
    $r->get('/documents/my-uploads', [ClientDocumentController::class, 'myUploads']);
    $r->get('/documents/trinova', [ClientDocumentController::class, 'trinovaDocs']);
    $r->get('/documents/view/{id}', [ClientDocumentController::class, 'view']);
    $r->get('/documents/download/{id}', [ClientDocumentController::class, 'download']);
    $r->get('/messages', [ClientMessageController::class, 'index']);
    $r->get('/messages/feed', [ClientMessageController::class, 'feed']);
    $r->post('/messages/send', [ClientMessageController::class, 'send'])->middleware([CsrfMiddleware::class]);
    $r->post('/messages/delete', [ClientMessageController::class, 'delete'])->middleware([CsrfMiddleware::class]);
    $r->get('/requests', [ClientRequestController::class, 'index']);
    $r->get('/deadlines', [ClientDeadlineController::class, 'index']);
    $r->get('/meetings/book', [ClientMeetingController::class, 'index']);
    $r->post('/meetings/book', [ClientMeetingController::class, 'book'])->middleware([CsrfMiddleware::class]);
    $r->post('/meetings/delete', [ClientMeetingController::class, 'delete'])->middleware([CsrfMiddleware::class]);
    $r->get('/aml', [ClientProfileController::class, 'aml']);
    $r->get('/profile/details', [ClientProfileController::class, 'details']);
    $r->post('/profile/request-update', [ClientProfileController::class, 'requestUpdate'])->middleware([CsrfMiddleware::class]);
    $r->post('/documents/delete', [ClientDocumentController::class, 'delete'])->middleware([CsrfMiddleware::class]);
});

// --- SECURED STAFF ROUTES ---
$app->router->group([
    'prefix' => '/staff',
    'middleware' => [AuthMiddleware::class, SessionTimeoutMiddleware::class, RoleMiddleware::class . ':staff']
], function($r) {
    $r->get('/dashboard', [StaffDashboardController::class, 'index']);
    $r->get('/clients', [StaffClientController::class, 'index']);
    $r->get('/clients/export', [StaffClientController::class, 'exportCsv']);
    $r->get('/clients/import', [StaffClientCsvController::class, 'index']);
    $r->get('/clients/import/template', [StaffClientCsvController::class, 'template']);
    $r->get('/clients/import-template', [StaffClientCsvController::class, 'template']);
    $r->post('/clients/import/upload', [StaffClientCsvController::class, 'upload'])->middleware([CsrfMiddleware::class]);
    $r->post('/clients/import/preview', [StaffClientCsvController::class, 'preview'])->middleware([CsrfMiddleware::class]);
    $r->post('/clients/import/commit', [StaffClientCsvController::class, 'commit'])->middleware([CsrfMiddleware::class]);
    $r->get('/clients/import/report.csv', [StaffClientCsvController::class, 'reportCsv']);
    $r->get('/clients/import/report/{id}', [StaffClientCsvController::class, 'showReport']);
    $r->get('/directors/import', [StaffDirectorCsvController::class, 'index']);
    $r->post('/directors/import/upload', [StaffDirectorCsvController::class, 'upload'])->middleware([CsrfMiddleware::class]);
    $r->post('/directors/import/commit', [StaffDirectorCsvController::class, 'commit'])->middleware([CsrfMiddleware::class]);
    $r->get('/directors/import/template', [StaffDirectorCsvController::class, 'template']);
    $r->get('/directors/import/report/{id}/download', [StaffDirectorCsvController::class, 'reportCsv']);
    $r->get('/directors/import/report/{id}', [StaffDirectorCsvController::class, 'report']);
    $r->post('/clients/create', [StaffClientController::class, 'create'])->middleware([CsrfMiddleware::class]);
    $r->post('/clients/reset-password', [StaffClientController::class, 'resetPassword'])->middleware([CsrfMiddleware::class]);
    $r->post('/clients/delete', [StaffClientController::class, 'delete'])->middleware([CsrfMiddleware::class]);
    $r->post('/clients/bulk-delete', [StaffClientController::class, 'bulkDelete'])->middleware([CsrfMiddleware::class]);
    $r->post('/clients/delete-entity', [StaffClientController::class, 'deleteEntity'])->middleware([CsrfMiddleware::class]);
    $r->post('/clients/import/batch/delete', [StaffClientCsvController::class, 'deleteBatch'])->middleware([CsrfMiddleware::class]);
    $r->get('/clients/{id}', [StaffClientController::class, 'show']);
    $r->post('/clients/add-entity', [StaffClientController::class, 'addEntity'])->middleware([CsrfMiddleware::class]);
    $r->post('/clients/link-director', [StaffClientController::class, 'linkDirector'])->middleware([CsrfMiddleware::class]);
    $r->post('/clients/unlink-director', [StaffClientController::class, 'unlinkDirector'])->middleware([CsrfMiddleware::class]);
    $r->get('/documents', [StaffDocumentController::class, 'index']);
    $r->post('/documents/upload', [StaffDocumentController::class, 'upload'])->middleware([CsrfMiddleware::class]);
    $r->get('/documents/download/{id}', [StaffDocumentController::class, 'download']);
    $r->post('/documents/delete', [StaffDocumentController::class, 'delete'])->middleware([CsrfMiddleware::class]);
    $r->post('/documents/bulk-delete', [StaffDocumentController::class, 'bulkDelete'])->middleware([CsrfMiddleware::class]);
    $r->get('/requests', [StaffRequestController::class, 'index']);
    $r->post('/requests/create', [StaffRequestController::class, 'create'])->middleware([CsrfMiddleware::class]);
    $r->post('/requests/update-status', [StaffRequestController::class, 'updateStatus'])->middleware([CsrfMiddleware::class]);
    $r->post('/requests/delete', [StaffRequestController::class, 'delete'])->middleware([CsrfMiddleware::class]);
    $r->post('/requests/bulk-delete', [StaffRequestController::class, 'bulkDelete'])->middleware([CsrfMiddleware::class]);
    $r->get('/messages', [StaffMessageController::class, 'index']);
    $r->get('/messages/feed', [StaffMessageController::class, 'feed']);
    $r->post('/messages/send', [StaffMessageController::class, 'send'])->middleware([CsrfMiddleware::class]);
    $r->post('/messages/delete', [StaffMessageController::class, 'delete'])->middleware([CsrfMiddleware::class]);
    $r->post('/messages/delete-thread', [StaffMessageController::class, 'deleteThread'])->middleware([CsrfMiddleware::class]);
    $r->post('/messages/bulk-delete', [StaffMessageController::class, 'bulkDelete'])->middleware([CsrfMiddleware::class]);
    $r->get('/deadlines', [StaffDeadlineController::class, 'index']);
    $r->post('/deadlines/create', [StaffDeadlineController::class, 'create'])->middleware([CsrfMiddleware::class]);
    $r->post('/deadlines/update-status', [StaffDeadlineController::class, 'updateStatus'])->middleware([CsrfMiddleware::class]);
    $r->post('/deadlines/delete', [StaffDeadlineController::class, 'delete'])->middleware([CsrfMiddleware::class]);
    $r->post('/deadlines/bulk-delete', [StaffDeadlineController::class, 'bulkDelete'])->middleware([CsrfMiddleware::class]);
    $r->get('/audit', [StaffAuditController::class, 'index']);
    $r->get('/users', [StaffUserAdminController::class, 'index']);
    $r->post('/users/create', [StaffUserAdminController::class, 'createUser'])->middleware([CsrfMiddleware::class]);
    $r->post('/users/toggle-status', [StaffUserAdminController::class, 'toggleStatus'])->middleware([CsrfMiddleware::class]);
    $r->post('/users/reset-password', [StaffUserAdminController::class, 'resetPassword'])->middleware([CsrfMiddleware::class]);
    $r->post('/users/resend-activation', [StaffUserAdminController::class, 'resendActivation'])->middleware([CsrfMiddleware::class]);
    $r->post('/users/delete', [StaffUserAdminController::class, 'delete'])->middleware([CsrfMiddleware::class]);
    $r->post('/users/bulk-delete', [StaffUserAdminController::class, 'bulkDelete'])->middleware([CsrfMiddleware::class]);
    $r->get('/trash', [StaffTrashController::class, 'index']);
    $r->post('/trash/restore', [StaffTrashController::class, 'restore'])->middleware([CsrfMiddleware::class]);
    $r->post('/trash/bulk-restore', [StaffTrashController::class, 'bulkRestore'])->middleware([CsrfMiddleware::class]);
    $r->get('/signatures', [StaffSignatureController::class, 'index']);
    $r->get('/signatures/create', [StaffSignatureController::class, 'showCreate']);
    $r->post('/signatures/create', [StaffSignatureController::class, 'processCreate'])->middleware([CsrfMiddleware::class]);
    $r->get('/signatures/{id}', [StaffSignatureController::class, 'show']);
    $r->post('/signatures/cancel', [StaffSignatureController::class, 'cancel'])->middleware([CsrfMiddleware::class]);
    $r->post('/signatures/resend', [StaffSignatureController::class, 'resend'])->middleware([CsrfMiddleware::class]);
});

// --- API V1 INTEGRATION ROUTES (HEADLESS / SLOANE INTEGRATION) ---
$app->router->group([
    'prefix' => '/api/v1',
    'middleware' => [ApiAuthMiddleware::class]
], function($r) {
    // Health & Auth Diagnostics
    $r->get('/health', [ApiHealthController::class, 'health']);
    $r->get('/auth/verify', [ApiHealthController::class, 'verify']);

    // Clients & Companies
    $r->get('/clients', [ApiClientController::class, 'index']);
    $r->get('/companies/{id}', [ApiClientController::class, 'show']);

    // Statutory Deadlines & Companies House Reconciliation
    $r->get('/statutory', [ApiStatutoryController::class, 'index']);
    $r->post('/statutory/sync', [ApiStatutoryController::class, 'sync']);

    // Internal Notes (with Strict Privacy Boundary & Idempotency)
    $r->post('/companies/{id}/notes', [ApiNoteController::class, 'create']);
});

// Security response headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-XSS-Protection: 1; mode=block');

// Boot application
$app->run();
