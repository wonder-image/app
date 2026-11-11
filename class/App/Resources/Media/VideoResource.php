<?php

namespace Wonder\App\Resources\Media;

use Wonder\App\Media\MediaTypes;
use Wonder\App\ResourceSchema\NavigationSchema;

/** Video della libreria: mp4, webm e mov salvati come sono, senza misure né miniature. */
final class VideoResource extends MediaResource
{
    public static array|string $condition = [
        'deleted' => 'false',
        'type' => MediaTypes::VIDEO,
    ];

    public static function path(): string
    {
        return 'app/media/videos';
    }

    public static function icon(): string
    {
        return 'bi bi-film';
    }

    public static function textSchema(): array
    {
        return [
            'label' => 'video',
            'plural_label' => 'video',
            'last' => 'ultimi',
            'all' => 'tutti',
            'article' => 'i',
            'full' => 'usato',
            'empty' => 'non usato',
            'this' => 'questo',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'file' => 'Video',
            'name' => 'Nome file',
            'slug' => 'Slug',
            'actions' => 'Azioni',
        ];
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('media')
            ->title('Video')
            ->order(45)
            ->authority(['admin']);
    }

    protected static function mediaType(): string
    {
        return MediaTypes::VIDEO;
    }

    protected static function hasPreview(): bool
    {
        return false;
    }
}
