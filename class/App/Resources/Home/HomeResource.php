<?php

namespace Wonder\App\Resources\Home;

use Wonder\App\Resources\Support\NavigationOnlyResource;
use Wonder\App\ResourceSchema\NavigationSchema;

/**
 * "Home" del backend: entry point al posto 0 del menu. Standalone (no
 * section), no subnav. Il file di destinazione viene dal global
 * `PERMITS['backend']['links']['home']` mantenuto dalla legacy auth.
 */
final class HomeResource extends NavigationOnlyResource
{
    public static function path(): string
    {
        return 'home';
    }

    public static function icon(): string
    {
        return 'bi-house-door';
    }

    public static function titleLabel(): string
    {
        return 'Home';
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->title('Home')
            ->sectionOrder(0)
            ->file(self::homeFile());
    }

    private static function homeFile(): string
    {
        $permits = $GLOBALS['PERMITS']['backend']['links']['home'] ?? '';

        return is_string($permits) ? $permits : '';
    }
}
