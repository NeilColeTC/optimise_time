<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, callable(): void> */
    private array $getRoutes = [];

    public function get(string $path, callable $handler): void
    {
        $this->getRoutes[$path] = $handler;
    }

    public function dispatch(string $method, string $path): void
    {
        if ($method === 'GET' && isset($this->getRoutes[$path])) {
            ($this->getRoutes[$path])();
            return;
        }

        http_response_code(404);
        echo 'Page not found';
    }
}

