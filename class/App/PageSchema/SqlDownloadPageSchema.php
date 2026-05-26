<?php

namespace Wonder\App\PageSchema;

use Wonder\App\ResourceSchema\FormField;

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
            'table' => FormField::key('table')->radio($tableOptions),
            'format' => FormField::key('format')->select([
                'csv' => 'Csv',
                'xls' => 'Excel',
            ]),
        ]);
    }
}
