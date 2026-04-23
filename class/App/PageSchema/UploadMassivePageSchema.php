<?php

namespace Wonder\App\PageSchema;

use Wonder\App\ResourceSchema\FormInput;

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
            'file' => FormInput::key('file')->inputFileDragDrop('image', 'classic'),
            'type' => FormInput::key('type')->select([
                'image' => 'Immagine',
                'icon' => 'Icona',
                'document' => 'Documento',
            ]),
        ]);
    }
}
