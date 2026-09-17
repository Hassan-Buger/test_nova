<?php

/**
 * TriNova Client Portal - Automated Database Migration CLI Script
 */

$appDir = dirname(__DIR__);

// Load Autoloader
$autoloadFound = false;
foreach ([$appDir . '/vendor/autoload.php', $appDir . '/8493files/vendor/autoload.php', $appDir . '/trinova_app/vendor/autoload.php'] as $autoloadFile) {
    if (file_exists($autoloadFile)) {
        require_once $autoloadFile;
        $autoloadFound = true;
        break;
    }
}

if (!$autoloadFound) {
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

// Load .env if present
$envFile = $appDir . '/.env';
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

use Application\Config\Database as DatabaseConfig;
use Application\Core\SchemaMigrator;

echo "[TriNova] Checking database connection and schema...\n";

$config = DatabaseConfig::getConfig();
$dsn = sprintf(
    "mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $config['host'],
    $config['port'],
    $config['dbname'],
    $config['charset']
);

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]);

    echo "[TriNova] Connected to database: {$config['dbname']} on {$config['host']}:{$config['port']}\n";
    $result = SchemaMigrator::run($pdo);

    if ($result) {
        echo "[TriNova] Database schema is ready and verified.\n";
    } else {
        echo "[TriNova] Notice: Database check finished with warnings.\n";
    }

    // Ensure seed artifacts exist in storage/uploads
    $storageDir = $appDir . '/storage';
    $seedsDir = $storageDir . '/seeds';
    $uploadsDir = $storageDir . '/uploads';
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0775, true);
    }
    if (is_dir($seedsDir)) {
        foreach (glob($seedsDir . '/*') as $seedFile) {
            $dest = $uploadsDir . '/' . basename($seedFile);
            if (!file_exists($dest)) {
                @copy($seedFile, $dest);
            }
        }
        echo "[TriNova] Seed document artifacts verified in storage/uploads.\n";
    }
} catch (PDOException $e) {
    echo "[TriNova] Database not reachable yet: " . $e->getMessage() . "\n";
}
