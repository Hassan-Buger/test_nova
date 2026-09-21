<?php

namespace Application\Config;

class App
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $autoUrl = 'http://localhost';
        if (!empty($_SERVER['HTTP_HOST'])) {
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443 || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https://' : 'http://';
            $autoUrl = $proto . $_SERVER['HTTP_HOST'];
        } elseif (!empty($_ENV['RAILWAY_PUBLIC_DOMAIN'])) {
            $autoUrl = 'https://' . $_ENV['RAILWAY_PUBLIC_DOMAIN'];
        } elseif (!empty($_ENV['RAILWAY_STATIC_URL'])) {
            $autoUrl = 'https://' . $_ENV['RAILWAY_STATIC_URL'];
        }

        $config = [
            'name' => 'TriNova Accounting',
            'url' => rtrim($_ENV['APP_URL'] ?? $autoUrl, '/') . '/',
            'env' => $_ENV['APP_ENV'] ?? (isset($_ENV['RAILWAY_ENVIRONMENT']) ? 'production' : 'local'),
            'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
            'secret' => $_ENV['APP_SECRET'] ?? 'trinova_default_secret_key_32bytes!',
            'session_timeout' => 900, // 15 minutes session inactivity timeout in seconds
            'storage_dir' => dirname(__DIR__, 2) . '/storage',
            'practice_key' => trim((string)($_ENV['PRACTICE_KEY'] ?? 'trinova-default')),
            'resend_api_key' => trim((string) ($_ENV['RESEND_API_KEY'] ?? '')),
            'email_from'     => trim((string) ($_ENV['RESEND_FROM'] ?? ($_ENV['EMAIL_FROM'] ?? 'TriNova Accounting <onboarding@resend.dev>'))),
            'api_key'        => trim((string)($_ENV['TRINOVA_API_KEY'] ?? 'tn_sec_live_sloane_readwrite_2026')),
        ];

        return $config[$key] ?? $default;
    }
}
