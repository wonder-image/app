<?php

namespace Wonder\Http;

class Router
{
    private array $routes = [];

    public function __construct(array $routes = [])
    {
        foreach ($routes as $route) {
            if (is_array($route)) {
                $this->add($route);
            }
        }
    }

    public function add(array $route): void
    {
        if (
            empty($route['path'])
            || (empty($route['handler']) && empty($route['redirect_to']))
        ) {
            return;
        }

        $route['path'] = $this->normalizePath((string) $route['path']);
        $route['method'] = isset($route['method']) && trim((string) $route['method']) !== ''
            ? strtoupper(trim((string) $route['method']))
            : null;

        $this->routes[] = $route;
    }

    public function match(string $method, string $path): ?array
    {
        $method = strtoupper(trim($method));
        $path = $this->normalizePath($path);

        foreach ($this->routes as $route) {
            if ($route['method'] !== null && $route['method'] !== $method) {
                continue;
            }

            $parameters = $this->matchPath($route, $path);

            if ($parameters === null) {
                continue;
            }

            $route['parameters'] = $parameters;

            return $route;
        }

        return null;
    }

    public function matchByPath(string $path): ?array
    {
        $path = $this->normalizePath($path);

        foreach ($this->routes as $route) {
            $parameters = $this->matchPath($route, $path);

            if ($parameters === null) {
                continue;
            }

            $route['parameters'] = $parameters;

            return $route;
        }

        return null;
    }

    private function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?? '/';
        $path = trim($path);

        if ($path === '') {
            return '/';
        }

        $path = '/'.trim($path, '/');

        return $path === '/' ? '/' : $path.'/';
    }

    private function matchPath(array $route, string $requestPath): ?array
    {
        $routePath = $this->normalizePath((string) ($route['path'] ?? '/'));

        if ($routePath === $requestPath) {
            return [];
        }

        $routeSegments = $this->segments($routePath);
        $requestSegments = $this->segments($requestPath);

        if (count($routeSegments) !== count($requestSegments)) {
            return null;
        }

        $parameters = [];

        foreach ($routeSegments as $index => $segment) {
            $requestSegment = $requestSegments[$index];

            if (preg_match('/^\{([a-zA-Z0-9_]+)\}$/', $segment, $matches)) {
                if (!$this->matchesPattern($matches[1], $requestSegment, $route)) {
                    return null;
                }
                $parameters[$matches[1]] = $requestSegment;
                continue;
            }

            if ($segment !== $requestSegment) {
                return null;
            }
        }

        return $parameters;
    }

    private function matchesPattern(string $parameter, string $value, array $route): bool
    {
        $where = isset($route['where']) && is_array($route['where']) ? $route['where'] : [];
        $pattern = $where[$parameter] ?? null;

        if (!is_string($pattern) || trim($pattern) === '') {
            return true;
        }

        return (bool) preg_match('/^'.$pattern.'$/', $value);
    }

    private function segments(string $path): array
    {
        $trimmed = trim($path, '/');

        return $trimmed === '' ? [] : explode('/', $trimmed);
    }
}
