<?php

namespace Application\Core;

class Router
{
    private array $routes = [];
    private array $groupAttributes = [];

    public function group(array $attributes, callable $callback): void
    {
        $parentGroupAttributes = $this->groupAttributes;
        
        $prefix = ($parentGroupAttributes['prefix'] ?? '') . ($attributes['prefix'] ?? '');
        $middleware = array_merge(
            $parentGroupAttributes['middleware'] ?? [],
            $attributes['middleware'] ?? []
        );

        $this->groupAttributes = [
            'prefix' => $prefix,
            'middleware' => $middleware
        ];

        call_user_func($callback, $this);

        $this->groupAttributes = $parentGroupAttributes;
    }

    public function get(string $path, array|callable $callback): self
    {
        $this->addRoute('GET', $path, $callback);
        return $this;
    }

    public function post(string $path, array|callable $callback): self
    {
        $this->addRoute('POST', $path, $callback);
        return $this;
    }

    public function patch(string $path, array|callable $callback): self
    {
        $this->addRoute('PATCH', $path, $callback);
        return $this;
    }

    public function put(string $path, array|callable $callback): self
    {
        $this->addRoute('PUT', $path, $callback);
        return $this;
    }

    public function delete(string $path, array|callable $callback): self
    {
        $this->addRoute('DELETE', $path, $callback);
        return $this;
    }

    public function options(string $path, array|callable $callback): self
    {
        $this->addRoute('OPTIONS', $path, $callback);
        return $this;
    }

    private function addRoute(string $method, string $path, array|callable $callback): void
    {
        $prefix = $this->groupAttributes['prefix'] ?? '';
        $fullPath = rtrim($prefix . $path, '/') ?: '/';
        $middleware = $this->groupAttributes['middleware'] ?? [];

        $this->routes[] = [
            'method'     => $method,
            'path'       => $fullPath,
            'callback'   => $callback,
            'middleware' => $middleware,
        ];
    }

    public function middleware(array $middleware): self
    {
        if (!empty($this->routes)) {
            $lastIndex = count($this->routes) - 1;
            $this->routes[$lastIndex]['middleware'] = array_merge(
                $this->routes[$lastIndex]['middleware'],
                $middleware
            );
        }
        return $this;
    }

    public function resolve(Request $request, Response $response): mixed
    {
        $method = $request->getMethod();
        $path = $request->getUri();

        // Immediate preflight termination for /api/v1 routes
        if ($method === 'OPTIONS' && str_starts_with($path, '/api/v1')) {
            if (!headers_sent()) {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization, Idempotency-Key, If-Match');
            }
            http_response_code(204);
            return null;
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            // Every current {id} route targets an integer controller argument.
            // Restrict it at the routing boundary so named sibling routes such
            // as /clients/import can never be consumed as an ID.
            $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', static function(array $match): string {
                $name=$match[1];
                $valuePattern=$name==='id'?'\\d+':'[a-zA-Z0-9_]+';
                return '(?P<'.$name.'>'.$valuePattern.')';
            }, $route['path']);
            $pattern = "#^" . $pattern . "$#";

            if (preg_match($pattern, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Execute Middleware Stack
                foreach ($route['middleware'] as $mw) {
                    $args = [];
                    if (str_contains($mw, ':')) {
                        [$mwClass, $argString] = explode(':', $mw, 2);
                        $args = explode(',', $argString);
                    } else {
                        $mwClass = $mw;
                    }

                    $middlewareInstance = new $mwClass();
                    $middlewareResult = $middlewareInstance->handle($request, $response, $args);
                    if ($middlewareResult === false) {
                        return null; // Pipeline terminated by middleware
                    }
                }

                $callback = $route['callback'];
                if (is_array($callback)) {
                    [$class, $action] = $callback;
                    $controller = new $class();
                    return call_user_func_array([$controller, $action], array_merge([$request, $response], $params));
                }

                return call_user_func_array($callback, array_merge([$request, $response], $params));
            }
        }

        if (str_starts_with($path, '/api/v1')) {
            if (!headers_sent()) {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization, Idempotency-Key, If-Match');
            }
            $response->json([
                'status' => 'error',
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'API endpoint not found.'
                ]
            ], 404);
            return null;
        }

        $response->setStatusCode(404);
        echo "404 - Page Not Found";
        return null;
    }
}
