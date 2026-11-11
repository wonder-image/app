<?php

namespace Wonder\App\Resources\Media;

use Wonder\App\Media\MediaTypes;
use Wonder\App\ResourceSchema\NavigationSchema;

/**
 * Icone vettoriali: solo svg, ripulito da `SvgSanitizer` al caricamento,
 * senza misure. Le icone raster stanno fra le immagini.
 */
final class IconResource extends MediaResource
{
    public static array|string $condition = [
        'deleted' => 'false',
        'type' => MediaTypes::ICON,
    ];

    public static function path(): string
    {
        return 'app/media/icons';
    }

    public static function icon(): string
    {
        return 'bi bi-grid-1x2';
    }

    public static function textSchema(): array
    {
        return [
            'label' => 'icona',
            'plural_label' => 'icone',
            'last' => 'ultime',
            'all' => 'tutte',
            'article' => 'le',
            'full' => 'usata',
            'empty' => 'non usata',
            'this' => 'questa',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'file' => 'Icona',
            'name' => 'File',
            'alt' => 'Alt',
            'slug' => 'Slug',
            'actions' => 'Azioni',
        ];
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('media')
            ->title('Icone')
            ->order(30)
            ->authority(['admin']);
    }

    protected static function mediaType(): string
    {
        return MediaTypes::ICON;
    }

    protected static function hasAlt(): bool
    {
        return true;
    }
}
