<?php

namespace Application\Controllers\Api;

use Application\Config\Database as DatabaseConfig;
use Application\Core\Request;
use Application\Core\Response;
use PDO;
use Throwable;

class HealthController
{
    public function health(Request $request, Response $response): void
    {
        $dbStatus = 'disconnected';
        try {
            $config = DatabaseConfig::getConfig();
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                $config['host'],
                $config['port'],
                $config['dbname'],
                $config['charset']
            );
            $pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 2,
            ]);
            $pdo->query("SELECT 1");
            $dbStatus = 'connected';
        } catch (Throwable $e) {
            $dbStatus = 'disconnected';
        }

        $response->json([
            'status' => 'success',
            'data' => [
                'healthy' => true,
                'app' => 'TriNova Accounting Client Portal',
                'version' => '1.0.0',
                'database' => $dbStatus,
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
            ]
        ], 200);
    }

    public function verify(Request $request, Response $response): void
    {
        $response->json([
            'status' => 'success',
            'data' => [
                'authenticated' => true,
                'actor' => 'sloane_ceo_command_centre',
                'scopes' => [
                    'read',
                    'write',
                    'statutory_reconcile',
                    'notes',
                    'signatures',
                    'documents'
                ],
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
            ]
        ], 200);
    }
}
