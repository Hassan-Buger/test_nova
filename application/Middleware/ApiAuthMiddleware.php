<?php

namespace Application\Middleware;

use Application\Config\App;
use Application\Core\Request;
use Application\Core\Response;

class ApiAuthMiddleware
{
    public function handle(Request $request, Response $response, array $args = []): bool
    {
        // Emit required CORS headers for all API requests
        if (!headers_sent()) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, Idempotency-Key, If-Match');
        }

        // Preflight OPTIONS requests terminate immediately with 204 No Content
        if ($request->getMethod() === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $uri = $request->getUri();
        $isPublic = in_array('public', $args, true)
            || $uri === '/api/v1/health'
            || str_starts_with($uri, '/api/v1/signing/webhooks')
            || str_starts_with($uri, '/api/v1/documents/download');

        if ($isPublic) {
            return true;
        }

        $token = $request->getBearerToken();
        $expectedKey = App::get('api_key', 'tn_sec_live_sloane_readwrite_2026');

        if (!$token || !hash_equals($expectedKey, $token)) {
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Invalid or missing Bearer token.'
                ]
            ], 401);
            return false;
        }

        return true;
    }
}
