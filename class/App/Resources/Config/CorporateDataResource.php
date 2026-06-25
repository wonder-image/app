<?php

namespace Wonder\App\Resources\Config;

use Wonder\App\Resources\Support\NavigationOnlyResource;
use Wonder\App\ResourceSchema\NavigationSchema;

/**
 * Voce "Dati aziendali" del menu Set Up. La pagina vera è gestita da
 * `Wonder\App\PageSchema\CorporateDataPageSchema`. Questa Resource
 * **dichiara la sezione "set-up"** per tutto il backend (è la prima
 * voce: title='Dati aziendali', order=10).
 */
final class CorporateDataResource extends NavigationOnlyResource
{
    public static function path(): string
    {
        return 'app/config/corporate-data';
    }

    public static function icon(): string
    {
        return 'bi-building';
    }

    public static function titleLabel(): string
    {
        return 'Dati aziendali';
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('set-up', 'Set Up', 'bi-gear', 1020, ['admin'])
            ->title('Dati aziendali')
            ->order(10)
            ->authority(['admin']);
    }
}
