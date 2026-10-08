<?php

declare(strict_types=1);

namespace App;

final class Router
{
    /** @var array<int, array{methods: string[], pattern: string, handler: callable, auth: bool}> */
    private array $routes = [];

    public function add(array $methods, string $pattern, callable $handler, bool $auth = true): void
    {
        $this->routes[] = [
            'methods' => array_map('strtoupper', $methods),
            'pattern' => $pattern,
            'handler' => $handler,
            'auth' => $auth,
        ];
    }

    public function get(string $pattern, callable $handler, bool $auth = true): void
    {
        $this->add(['GET'], $pattern, $handler, $auth);
    }

    public function post(string $pattern, callable $handler, bool $auth = true): void
    {
        $this->add(['POST'], $pattern, $handler, $auth);
    }

    public function patch(string $pattern, callable $handler, bool $auth = true): void
    {
        $this->add(['PATCH'], $pattern, $handler, $auth);
    }

    public function delete(string $pattern, callable $handler, bool $auth = true): void
    {
        $this->add(['DELETE'], $pattern, $handler, $auth);
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes as $route) {
            if (!in_array($method, $route['methods'], true)) {
                continue;
            }

            $regex = '#^' . preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $route['pattern']) . '$#';
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_int($key)) {
                    $params[$key] = urldecode($value);
                }
            }

            if ($route['auth']) {
                Auth::requireAdmin($request);
            }

            ($route['handler'])($request, $params);
            return;
        }

        Response::fail('Not found', 404);
    }
}
