<?php

namespace Application\Services;

use Application\Config\App;
use Throwable;

class WebhookService
{
    /**
     * Dispatch an event webhook to Sloane.
     *
     * @param string $event Event identifier (e.g. 'task.updated')
     * @param array $data Task or payload data
     * @return bool Whether the dispatch request was initiated
     */
    public static function dispatch(string $event, array $data): bool
    {
        $webhookUrl = trim((string)($_ENV['TASK_WEBHOOK_URL'] ?? App::get('task_webhook_url', '')));
        if ($webhookUrl === '') {
            // Default Sloane webhook endpoint
            $webhookUrl = 'https://sloanetrinovaintegration-production.up.railway.app/api/v1/integrations/trinova/webhooks/tasks';
        }

        $secret = trim((string)($_ENV['TASK_WEBHOOK_SECRET'] ?? App::get('task_webhook_secret', 'whsec_9881574c0cfa54453f201e4f9e4aff6913db6c2baaacc5d2c857e9ed4a573e91')));

        $payload = [
            'event'     => $event,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'data'      => $data
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($jsonPayload === false) {
            return false;
        }

        $signature = 'sha256=' . hash_hmac('sha256', $jsonPayload, $secret);

        try {
            $ch = curl_init($webhookUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $jsonPayload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($jsonPayload),
                    'X-Trinova-Signature: ' . $signature,
                    'User-Agent: TriNova-Portal/1.0 Webhook-Dispatcher'
                ],
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            // Execute non-blocking where possible or short timeout
            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            error_log("[TriNova WebhookService] Dispatched event '{$event}' to {$webhookUrl} (HTTP {$httpCode})");
            return $httpCode >= 200 && $httpCode < 300;
        } catch (Throwable $e) {
            error_log("[TriNova WebhookService] Webhook dispatch notice: " . $e->getMessage());
            return false;
        }
    }
}