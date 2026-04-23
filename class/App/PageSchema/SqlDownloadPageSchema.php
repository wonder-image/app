<?php

namespace Wonder\App\PageSchema;

use Wonder\App\ResourceSchema\FormInput;

final class SqlDownloadPageSchema extends CustomPageSchema
{
    public static function labelSchema(): array
    {
        return [
            'table' => 'Tabella',
            'format' => 'Formato',
        ];
    }

    public static function formSchema(array $tableOptions): array
    {
        return static::applyLabelSchema([
            'table' => FormInput::key('table')->radio($tableOptions),
            'format' => FormInput::key('format')->select([
                'csv' => 'Csv',
                'xls' => 'Excel',
            ]),
        ]);
    }
}
