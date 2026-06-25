<?php

namespace Wonder\App\Support;

use Wonder\App\LegacyGlobals;
use Wonder\Http\Route;

final class FrontendRouteCatalog
{
    private static ?array $frontendRoutes = null;

    public static function currentPageKey(): ?string
    {
        $routeMeta = LegacyGlobals::get('ROUTE_META', []);

        if (!is_array($routeMeta) || trim((string) ($routeMeta['area'] ?? '')) !== 'frontend') {
            return null;
        }

        $name = trim((string) ($routeMeta['name'] ?? ''));

        return $name !== '' ? $name : null;
    }

    public static function options(): array
    {
        $options = [];

        foreach (self::routes() as $route) {
            $name = trim((string) ($route['name'] ?? ''));

            if ($name === '' || isset($options[$name])) {
                continue;
            }

            $path = (string) ($route['_canonical_path'] ?? $route['path'] ?? '/');
            $options[$name] = $name.' ('.$path.')';
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    public static function routes(): array
    {
        if (self::$frontendRoutes !== null) {
            return self::$frontendRoutes;
        }

        $loadedRoutes = Route::all();

        if ($loadedRoutes !== []) {
            self::$frontendRoutes = array_values(array_filter(
                $loadedRoutes,
                static fn (mixed $route): bool => is_array($route)
                    && trim((string) ($route['area'] ?? '')) === 'frontend'
            ));

            return self::$frontendRoutes;
        }

        $root = trim((string) LegacyGlobals::get('ROOT', ''));
        $rootApp = trim((string) LegacyGlobals::get('ROOT_APP', ''));

        if ($root === '' || $rootApp === '') {
            return self::$frontendRoutes = [];
        }

        $directories = array_values(array_filter([
            $rootApp.'/config/routes',
            $root.'/custom/routes',
            $root.'/custom/config/routes',
        ], static fn (string $directory): bool => is_dir($directory)));

        if ($directories === []) {
            return self::$frontendRoutes = [];
        }

        Route::loadDirectories($directories, [
            'ROOT' => $root,
            'ROOT_APP' => $rootApp,
        ]);

        self::$frontendRoutes = array_values(array_filter(
            Route::all(),
            static fn (mixed $route): bool => is_array($route)
                && trim((string) ($route['area'] ?? '')) === 'frontend'
        ));

        return self::$frontendRoutes;
    }
}
