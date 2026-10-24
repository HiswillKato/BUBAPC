<?php
// AI-assisted
declare(strict_types=1);

namespace Martazi\core;

use InvalidArgumentException;

final class Router
{
    private array $routes = [];
    private mixed $notFound = null;

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function add(string $method, string $path, callable $handler): void
    {
        if (!str_starts_with($path, '/')) {
            throw new InvalidArgumentException('Invalid route path');
        }

        $this->routes[strtoupper($method)][] = [$path, $handler];
    }

    public function setNotFound(callable $handler): void
    {
        $this->notFound = $handler;
    }

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // HEAD requests use GET routes without a response body.
        if ($method === 'HEAD') {
            ob_start();

            try {
                $this->run('GET', $path);
            } finally {
                ob_end_clean();
            }

            return;
        }

        $this->run($method, $path);
    }

    private function run(string $method, string $path): void
    {
        foreach ($this->routes[$method] ?? [] as [$route, $handler]) {
            $pattern = preg_replace(
                '/\{(\w+)\}/',
                '(?P<$1>[^/]+)',
                preg_quote($route, '~')
            );

            // preg_quote() escapes braces, so remove their escapes
            // before replacing route placeholders.
            $pattern = preg_replace(
                '/\\\\\{(\w+)\\\\\}/',
                '(?P<$1>[^/]+)',
                preg_quote($route, '~')
            );

            if (preg_match('~^' . $pattern . '$~D', $path, $matches)) {
                $params = array_filter(
                    $matches,
                    'is_string',
                    ARRAY_FILTER_USE_KEY
                );

                call_user_func_array($handler, $params);
                return;
            }
        }

        http_response_code(404);
        if ($this->notFound) {
            call_user_func($this->notFound);
            return;
        }
        echo '404 - Page Not Found';
    }
}
