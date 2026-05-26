<?php

namespace Wonder\App\Resources\Media;

use Wonder\App\Resources\Support\NavigationOnlyResource;
use Wonder\App\ResourceSchema\NavigationSchema;

/**
 * Voce "Upload di massa" del menu Media. La pagina vera è gestita da
 * `Wonder\App\PageSchema\UploadMassivePageSchema`; questa Resource
 * esiste solo per dichiarare il link nella navigation.
 */
final class UploadMassiveResource extends NavigationOnlyResource
{
    public static function path(): string
    {
        return 'app/media/upload-massive';
    }

    public static function icon(): string
    {
        return 'bi-cloud-upload';
    }

    public static function titleLabel(): string
    {
        return 'Upload di massa';
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('media')
            ->title('Upload di massa')
            ->order(50)
            ->authority(['admin']);
    }
}
