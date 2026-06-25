<?php

namespace Wonder\App\PageSchema;

use Wonder\App\ResourceSchema\FormField;

final class UploadMassivePageSchema extends CustomPageSchema
{
    public static function labelSchema(): array
    {
        return [
            'file' => 'File',
            'type' => 'Tipologia',
        ];
    }

    public static function formSchema(): array
    {
        return static::applyLabelSchema([
            'file' => FormField::key('file')->fileDragDrop('media', 'classic'),
            'type' => FormField::key('type')->select([
                'image' => 'Immagine',
                'icon' => 'Icona',
                'document' => 'Documento',
            ]),
        ]);
    }
}
