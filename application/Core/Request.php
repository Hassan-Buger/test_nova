<?php

namespace Application\Core;

class Request
{
    public function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function getUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $position = strpos($uri, '?');
        if ($position !== false) {
            $uri = substr($uri, 0, $position);
        }

        $uri = rawurldecode($uri);
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = str_replace('\\', '/', dirname($scriptName));
        if ($scriptDir !== '/' && $scriptDir !== '.' && strpos($uri, $scriptDir) === 0) {
            $uri = substr($uri, strlen($scriptDir));
        }

        return rtrim($uri, '/') ?: '/';
    }

    private ?array $jsonBody = null;

    public function getBody(): array
    {
        $body = [];
        if ($this->getMethod() === 'GET') {
            foreach ($_GET as $key => $value) {
                $body[$key] = is_string($value) ? trim($value) : $value;
            }
        }
        if ($this->getMethod() === 'POST') {
            foreach ($_POST as $key => $value) {
                $body[$key] = is_string($value) ? trim($value) : $value;
            }
        }
        return $body;
    }

    public function getJsonBody(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }

        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            $this->jsonBody = [];
            return $this->jsonBody;
        }

        $decoded = json_decode($raw, true);
        $this->jsonBody = is_array($decoded) ? $decoded : [];
        return $this->jsonBody;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $json = $this->getJsonBody();
        if (array_key_exists($key, $json)) {
            return $json[$key];
        }
        $body = $this->getBody();
        return $body[$key] ?? $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public function getHeader(string $name): ?string
    {
        $nameUpper = strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER['HTTP_' . $nameUpper])) {
            return (string)$_SERVER['HTTP_' . $nameUpper];
        }
        if (isset($_SERVER[$nameUpper])) {
            return (string)$_SERVER[$nameUpper];
        }
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $key => $value) {
                    if (strcasecmp($key, $name) === 0) {
                        return (string)$value;
                    }
                }
            }
        }
        return null;
    }

    public function getBearerToken(): ?string
    {
        $authHeader = $this->getHeader('Authorization');
        if ($authHeader && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function getQueryParams(): array
    {
        return $_GET ?? [];
    }

    public function isAjax(): bool
    {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || isset($_POST['is_ajax']) || isset($_GET['is_ajax']);
    }

    public function getIp(): string
    {
        return $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
