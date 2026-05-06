<?php

namespace Wonder\App;

use Wonder\App\Module\Registry;

final class ModuleRouteRegistrar
{
    public static function registerFrontend(string $root, string $rootApp): void
    {
        self::register('frontend', $root, $rootApp);
    }

    public static function registerBackend(string $root, string $rootApp): void
    {
        self::register('backend', $root, $rootApp);
    }

    public static function registerApi(string $root, string $rootApp): void
    {
        self::register('api', $root, $rootApp);
    }

    private static function register(string $area, string $root, string $rootApp): void
    {
        $ROOT = $root;
        $ROOT_APP = $rootApp;

        foreach (Registry::routeFiles($area) as $file) {
            require $file;
        }
    }
}
