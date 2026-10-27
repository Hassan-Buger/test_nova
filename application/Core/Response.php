<?php

namespace Application\Core;

class Response
{
    public function setStatusCode(int $code): void
    {
        http_response_code($code);
    }

    public function setHeader(string $name, string $value): void
    {
        header("$name: $value");
    }

    public function redirect(string $url): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header("Location: $url");
        exit;
    }

    public function json(array $data, int $code = 200): void
    {
        $this->setStatusCode($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
