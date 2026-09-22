<?php

namespace Application\Core;

use Application\Config\Database as DatabaseConfig;
use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function setInstance(?PDO $instance): void
    {
        self::$instance = $instance;
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = DatabaseConfig::getConfig();
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                $config['host'],
                $config['port'],
                $config['dbname'],
                $config['charset']
            );

            try {
                self::$instance = new PDO($dsn, $config['username'], $config['password'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                ]);

                // Automatically ensure schema & default seed accounts are populated
                SchemaMigrator::run(self::$instance);
            } catch (PDOException $e) {
                // Preserve full diagnostics in the server log
                error_log(sprintf(
                    '[%s] Database connection failure: %s (Host: %s:%s, DB: %s, User: %s)',
                    date('c'),
                    $e->getMessage(),
                    $config['host'],
                    $config['port'],
                    $config['dbname'],
                    $config['username']
                ));
                self::renderSetupPage($config, $e->getMessage());
                exit;
            }
        }

        return self::$instance;
    }

    private static function renderSetupPage(array $config, string $errorMessage): void
    {
        http_response_code(500);
        $isLocalhost = in_array($config['host'], ['127.0.0.1', 'localhost', '::1'], true);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Database Setup Required — TriNova Portal</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
            <style>
                body { margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 24px; box-sizing: border-box; }
                .card { background: #1e293b; border-radius: 20px; max-width: 640px; width: 100%; padding: 32px; box-shadow: 0 20px 40px -15px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); }
                .badge { display: inline-block; background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); font-weight: 700; font-size: 11.5px; padding: 4px 10px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 14px; }
                h1 { margin: 0 0 10px; font-size: 22px; font-weight: 800; color: #fff; }
                p { margin: 0 0 18px; color: #94a3b8; font-size: 14px; line-height: 1.6; }
                .status-box { background: #0f172a; border-radius: 12px; padding: 16px; font-size: 13px; color: #cbd5e1; border: 1px solid rgba(255,255,255,0.06); margin-bottom: 20px; }
                .status-row { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid rgba(255,255,255,0.04); }
                .status-row:last-child { border-bottom: none; }
                .status-label { color: #64748b; font-weight: 500; }
                .status-val { font-family: monospace; color: #38bdf8; font-weight: 600; }
                .steps { background: rgba(30, 41, 59, 0.8); border-radius: 12px; padding: 18px 20px; font-size: 13.5px; line-height: 1.6; border: 1px solid rgba(255,255,255,0.08); }
                .steps h3 { margin: 0 0 10px; font-size: 14px; color: #f1f5f9; }
                .steps ol { margin: 0; padding-left: 20px; color: #cbd5e1; }
                .steps li { margin-bottom: 8px; }
                .steps code { background: #0f172a; color: #a78bfa; padding: 2px 6px; border-radius: 5px; font-family: monospace; font-size: 12px; border: 1px solid rgba(255,255,255,0.1); }
            </style>
        </head>
        <body>
            <div class="card">
                <span class="badge">Database Connection Required</span>
                <h1>Connect Your MySQL Database</h1>
                <p>The PHP Web Service is running, but cannot connect to MySQL database yet. Once connected, TriNova will <strong>automatically create tables and seed default accounts</strong>.</p>
                
                <div class="status-box">
                    <div class="status-row">
                        <span class="status-label">Detected DB Host:</span>
                        <span class="status-val"><?= htmlspecialchars($config['host'] . ':' . $config['port']) ?> <?= $isLocalhost ? '(Unlinked / Default)' : '' ?></span>
                    </div>
                    <div class="status-row">
                        <span class="status-label">Database Name:</span>
                        <span class="status-val"><?= htmlspecialchars($config['dbname']) ?></span>
                    </div>
                    <div class="status-row">
                        <span class="status-label">Database User:</span>
                        <span class="status-val"><?= htmlspecialchars($config['username']) ?></span>
                    </div>
                </div>

                <div class="steps">
                    <h3>⚡ 2-Minute Setup on Railway:</h3>
                    <ol>
                        <li>In Railway Project Canvas, click <strong>+ New → Database → MySQL</strong>.</li>
                        <li>Click your PHP service (<code>trinova_client_portal</code>) → <strong>Variables</strong> tab.</li>
                        <li>Add variable <code>MYSQL_URL</code> with value <code>${{MySQL.MYSQL_URL}}</code> (or connect the database).</li>
                        <li>Railway will auto-restart the service and TriNova will automatically run migrations!</li>
                    </ol>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}
