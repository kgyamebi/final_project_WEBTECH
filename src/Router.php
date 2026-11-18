<?php
declare(strict_types=1);

namespace App;

final class Router
{
    /** @var array<int, array{0:string,1:string,2:array{0:class-string,1:string}}> */
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->map(['GET'], $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->map(['POST'], $path, $handler);
    }

    public function patch(string $path, array $handler): void
    {
        $this->map(['PATCH', 'POST'], $path, $handler);
    }

    /** @param list<string> $methods */
    private function map(array $methods, string $path, array $handler): void
    {
        foreach ($methods as $method) {
            $this->routes[] = [$method, $path, $handler];
        }
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/') ?: '/';
        }
        foreach ($this->routes as [$routeMethod, $routePath, $handler]) {
            $pattern = preg_replace('#\{([A-Za-z_]+)\}#', '(?P<$1>[^/]+)', $routePath);
            if ($routeMethod !== $method || !preg_match('#^' . $pattern . '$#', $path, $matches)) {
                continue;
            }
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            [$class, $action] = $handler;
            (new $class())->$action($params);
            return;
        }
        abort(404, 'That page is not in InsideAccra.');
    }
}
