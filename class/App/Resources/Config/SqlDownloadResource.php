<?php

namespace Wonder\App\Resources\Config;

use Wonder\App\Resources\Support\NavigationOnlyResource;
use Wonder\App\ResourceSchema\NavigationSchema;

/**
 * Voce "Download" del menu Dev. La pagina vera è gestita da
 * `Wonder\App\PageSchema\SqlDownloadPageSchema`. Questa Resource
 * esiste solo per dichiarare il link nella navigation.
 */
final class SqlDownloadResource extends NavigationOnlyResource
{
    public static function path(): string
    {
        return 'app/config/sql-download';
    }

    public static function icon(): string
    {
        return 'bi-download';
    }

    public static function titleLabel(): string
    {
        return 'Download';
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('dev')
            ->title('Download')
            ->order(60)
            ->authority(['admin']);
    }
}
