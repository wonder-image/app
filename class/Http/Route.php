<?php

namespace Wonder\Http;

class Route
{
    private static array $routes = [];
    private static array $groups = [];
    private static array $namedRoutes = [];

    public static function reset(): void
    {
        self::$routes = [];
        self::$groups = [];
        self::$namedRoutes = [];
    }

    public static function load(array $files, array $context = []): array
    {
        self::reset();

        foreach ($files as $file) {
            if (!is_string($file) || $file === '' || !file_exists($file)) {
                continue;
            }

            self::loadFile($file, $context);
        }

        return self::$routes;
    }

    public static function loadDirectories(array $directories, array $context = []): array
    {
        $files = [];

        foreach ($directories as $directory) {
            if (!is_string($directory) || $directory === '' || !is_dir($directory)) {
                continue;
            }

            foreach (glob(rtrim($directory, '/').'/route.*.php') ?: [] as $file) {
                if (is_string($file)) {
                    $files[] = $file;
                }
            }
        }

        sort($files);

        return self::load(array_values(array_unique($files)), $context);
    }

    public static function all(): array
    {
        return self::$routes;
    }

    public static function area(string $area): RouteGroup
    {
        return (new RouteGroup())->area($area);
    }

    public static function response(string $response): RouteGroup
    {
        return (new RouteGroup())->response($response);
    }

    public static function theme(?string $theme): RouteGroup
    {
        return (new RouteGroup())->theme($theme);
    }

    public static function guarded(bool $guarded = true): RouteGroup
    {
        return (new RouteGroup())->guarded($guarded);
    }

    public static function permit(array $permit): RouteGroup
    {
        return (new RouteGroup())->permit($permit);
    }

    public static function name(string $name): RouteGroup
    {
        return (new RouteGroup())->name($name);
    }

    public static function prefix(string $prefix): RouteGroup
    {
        return (new RouteGroup())->prefix($prefix);
    }

    public static function path(string $path): RouteGroup
    {
        return (new RouteGroup())->path($path);
    }

    public static function redirect(string $path, string $to, int $status = 302, array $attributes = []): RouteDefinition
    {
        return self::add('GET', $path, null, array_merge($attributes, [
            'redirect_to' => $to,
            'redirect_status' => $status,
        ]));
    }

    public static function get(string $path, string $handler, array $attributes = []): RouteDefinition
    {
        return self::add('GET', $path, $handler, $attributes);
    }

    public static function post(string $path, string $handler, array $attributes = []): RouteDefinition
    {
        return self::add('POST', $path, $handler, $attributes);
    }

    public static function put(string $path, string $handler, array $attributes = []): RouteDefinition
    {
        return self::add('PUT', $path, $handler, $attributes);
    }

    public static function patch(string $path, string $handler, array $attributes = []): RouteDefinition
    {
        return self::add('PATCH', $path, $handler, $attributes);
    }

    public static function delete(string $path, string $handler, array $attributes = []): RouteDefinition
    {
        return self::add('DELETE', $path, $handler, $attributes);
    }

    public static function group(array $attributes, callable $callback): void
    {
        self::$groups[] = $attributes;

        try {
            $callback();
        } finally {
            array_pop(self::$groups);
        }
    }

    public static function url(string $name, array $parameters = []): string
    {
        $route = self::$namedRoutes[$name] ?? null;

        if (!is_array($route) || empty($route['path'])) {
            return '';
        }

        $path = (string) $route['path'];

        foreach ($parameters as $key => $value) {
            $path = str_replace('{'.$key.'}', rawurlencode((string) $value), $path);
        }

        if (defined('APP_URL')) {
            return rtrim((string) APP_URL, '/').$path;
        }

        return $path;
    }

    public static function update(int $index, array $attributes): void
    {
        if (!isset(self::$routes[$index])) {
            return;
        }

        self::$routes[$index] = self::mergeAttributes(self::$routes[$index], $attributes);

        if (!empty(self::$routes[$index]['name']) && is_string(self::$routes[$index]['name'])) {
            self::$namedRoutes[self::$routes[$index]['name']] = self::$routes[$index];
        }
    }

    public static function updatePath(int $index, string $path): void
    {
        if (!isset(self::$routes[$index])) {
            return;
        }

        $prefix = (string) (self::$routes[$index]['_group_prefix'] ?? '');
        self::$routes[$index]['path'] = self::normalizePath(self::prefixPath($prefix, $path));
    }

    public static function assignName(int $index, string $name): void
    {
        if (!isset(self::$routes[$index])) {
            return;
        }

        $fullName = self::prefixName((string) (self::$routes[$index]['_group_name'] ?? ''), $name);

        self::$routes[$index]['name'] = $fullName;

        if ($fullName !== '') {
            self::$namedRoutes[$fullName] = self::$routes[$index];
        }
    }

    public static function applyWhere(int $index, string|array $parameter, ?string $pattern = null): void
    {
        if (!isset(self::$routes[$index])) {
            return;
        }

        $where = is_array($parameter)
            ? $parameter
            : [ $parameter => $pattern ];

        $current = isset(self::$routes[$index]['where']) && is_array(self::$routes[$index]['where'])
            ? self::$routes[$index]['where']
            : [];

        self::$routes[$index]['where'] = array_merge($current, $where);
    }

    public static function addMasks(int $index, string|array $paths, int $status = 301): void
    {
        if (!isset(self::$routes[$index])) {
            return;
        }

        $maskPaths = is_array($paths) ? $paths : [ $paths ];
        $route = self::$routes[$index];
        $canonicalPath = (string) ($route['path'] ?? '');
        $groupPrefix = (string) ($route['_group_prefix'] ?? '');

        foreach ($maskPaths as $maskPath) {
            if (!is_string($maskPath) || trim($maskPath) === '') {
                continue;
            }

            $resolvedMaskPath = self::resolveMaskedPath($groupPrefix, $maskPath);

            if ($resolvedMaskPath === $canonicalPath) {
                continue;
            }

            self::add((string) ($route['method'] ?? 'GET'), $resolvedMaskPath, null, [
                'area' => $route['area'] ?? null,
                'private' => false,
                'permit' => [],
                'response' => $route['response'] ?? 'html',
                'theme' => $route['theme'] ?? null,
                'frontend' => $route['frontend'] ?? false,
                'backend' => $route['backend'] ?? false,
                'where' => $route['where'] ?? [],
                'redirect_to' => $canonicalPath,
                'redirect_status' => $status,
            ]);
        }
    }

    private static function add(string $method, string $path, ?string $handler, array $attributes = []): RouteDefinition
    {
        $route = array_merge(
            self::defaultAttributes(),
            self::mergeGroupAttributes(),
            $attributes
        );

        $route['method'] = strtoupper($method);
        $route['_group_prefix'] = self::groupPathPrefix();
        $route['_group_name'] = self::groupNamePrefix();
        $route['path'] = self::normalizePath(self::prefixPath((string) $route['_group_prefix'], $path));
        $route['handler'] = $handler;
        $route['name'] = self::prefixName((string) $route['_group_name'], (string) ($route['name'] ?? ''));

        self::$routes[] = $route;
        $index = array_key_last(self::$routes);

        if (!empty($route['name']) && is_string($route['name'])) {
            self::$namedRoutes[$route['name']] = $route;
        }

        return new RouteDefinition((int) $index);
    }

    private static function defaultAttributes(): array
    {
        return [
            'area' => null,
            'private' => false,
            'permit' => [],
            'response' => 'html',
            'theme' => null,
            'name' => null,
            'frontend' => false,
            'backend' => false,
            'where' => [],
            'redirect_to' => null,
            'redirect_status' => 302,
        ];
    }

    private static function mergeGroupAttributes(): array
    {
        $merged = [];

        foreach (self::$groups as $group) {
            $merged = self::mergeAttributes($merged, $group);
        }

        return $merged;
    }

    private static function mergeAttributes(array $base, array $attributes): array
    {
        $merged = array_merge($base, $attributes);

        if (isset($base['permit']) || isset($attributes['permit'])) {
            $basePermit = isset($base['permit']) && is_array($base['permit']) ? $base['permit'] : [];
            $currentPermit = isset($attributes['permit']) && is_array($attributes['permit']) ? $attributes['permit'] : [];
            $merged['permit'] = !empty($currentPermit) ? $currentPermit : $basePermit;
        }

        if (isset($base['where']) || isset($attributes['where'])) {
            $baseWhere = isset($base['where']) && is_array($base['where']) ? $base['where'] : [];
            $currentWhere = isset($attributes['where']) && is_array($attributes['where']) ? $attributes['where'] : [];
            $merged['where'] = array_merge($baseWhere, $currentWhere);
        }

        return $merged;
    }

    private static function groupPathPrefix(): string
    {
        $prefix = '';

        foreach (self::$groups as $group) {
            $segment = (string) ($group['prefix'] ?? $group['path'] ?? '');

            if ($segment !== '') {
                $prefix = self::prefixPath($prefix, $segment);
            }
        }

        return $prefix;
    }

    private static function groupNamePrefix(): string
    {
        $prefix = '';

        foreach (self::$groups as $group) {
            $name = trim((string) ($group['name'] ?? ''));

            if ($name !== '') {
                $prefix .= $name;
            }
        }

        return $prefix;
    }

    private static function prefixPath(string $prefix, string $path): string
    {
        $prefix = trim($prefix);
        $path = trim($path);

        if ($prefix === '') {
            return $path;
        }

        if ($path === '' || $path === '/') {
            return $prefix;
        }

        return rtrim($prefix, '/').'/'.ltrim($path, '/');
    }

    private static function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?? '/';
        $path = trim($path);

        if ($path === '') {
            return '/';
        }

        $path = '/'.trim($path, '/');

        return $path === '/' ? '/' : $path.'/';
    }

    private static function prefixName(string $prefix, string $name): string
    {
        $prefix = trim($prefix);
        $name = trim($name);

        if ($prefix === '') {
            return $name;
        }

        if ($name === '') {
            return $prefix;
        }

        return $prefix.$name;
    }

    private static function resolveMaskedPath(string $groupPrefix, string $maskPath): string
    {
        $normalizedMaskPath = self::normalizePath($maskPath);
        $normalizedGroupPrefix = self::normalizePath($groupPrefix);

        if (
            $normalizedGroupPrefix !== '/'
            && str_starts_with($normalizedMaskPath, $normalizedGroupPrefix)
        ) {
            return $normalizedMaskPath;
        }

        return self::normalizePath(self::prefixPath($groupPrefix, $maskPath));
    }

    private static function loadFile(string $file, array $context = []): void
    {
        extract($context, EXTR_SKIP);
        require $file;
    }
}
