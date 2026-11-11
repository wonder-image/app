<?php

namespace Wonder\App\Resources\Media;

use Wonder\App\Media\MediaTypes;
use Wonder\App\ResourceSchema\NavigationSchema;

final class ImageResource extends MediaResource
{
    public static array|string $condition = [
        'deleted' => 'false',
        'type' => MediaTypes::IMAGE,
    ];

    public static function path(): string
    {
        return 'app/media/images';
    }

    public static function icon(): string
    {
        return 'bi bi-image';
    }

    public static function textSchema(): array
    {
        return [
            'label' => 'immagine',
            'plural_label' => 'immagini',
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
            'file' => 'Immagine',
            'name' => 'File',
            'slug' => 'Slug',
            'actions' => 'Azioni',
        ];
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('media')
            ->title('Immagini')
            ->order(20)
            ->authority(['admin']);
    }

    protected static function mediaType(): string
    {
        return MediaTypes::IMAGE;
    }
}
