<?php

namespace Wonder\App\Models\Css;

use Wonder\App\Model;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class CssAuth extends Model
{
    public static string $table = 'css_auth';
    public static string $folder = 'app/css/auth';
    public static string $icon = 'bi bi-person-lock';

    public static function syncSchema(): ?SyncSchema
    {
        return SyncSchema::singleton();
    }

    public static function tableSchema(): array
    {
        return [
            Column::key('bg_color'),
            Column::key('tx_color'),
            Column::key('form_bg_color'),
            Column::key('form_tx_color'),
            Column::key('form_border_color'),
        ];
    }

    public static function dataSchema(): array
    {
        return array_map(
            static fn (string $column) => Field::key($column)->text()->required(),
            array_keys(static::getColumns())
        );
    }
}
