<?php

namespace Wonder\App\Resources\Support;

use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\Support\CssConfigSync;

abstract class CssSingleton extends SingletonResource
{
    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('css', 'Stile', 'bi-award', 1010, ['admin'])
            ->authority(['admin']);
    }

    public static function afterUpdate(int|string $id, object $result, array $values = []): void
    {
        static::refreshCss();
    }

    protected static function refreshCss(): void
    {
        if (function_exists('cssRoot')) {
            cssRoot();
        }

        CssConfigSync::autoExport();
    }
}
