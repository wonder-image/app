<?php

namespace Wonder\App\Resources\Media;

use Wonder\App\Media\MediaTypes;
use Wonder\App\ResourceSchema\NavigationSchema;

final class DocumentResource extends MediaResource
{
    public static array|string $condition = [
        'deleted' => 'false',
        'type' => MediaTypes::DOCUMENT,
    ];

    public static function path(): string
    {
        return 'app/media/documents';
    }

    public static function icon(): string
    {
        return 'bi bi-file-earmark-text';
    }

    public static function textSchema(): array
    {
        return [
            'label' => 'documento',
            'plural_label' => 'documenti',
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
            'file' => 'Documento',
            'name' => 'Nome file',
            'slug' => 'Slug',
            'actions' => 'Azioni',
        ];
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('media', 'Media', 'bi-image', 1000, ['admin'])
            ->title('Documenti')
            ->order(40)
            ->authority(['admin']);
    }

    protected static function mediaType(): string
    {
        return MediaTypes::DOCUMENT;
    }

    protected static function hasPreview(): bool
    {
        return false;
    }
}
