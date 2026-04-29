<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class SqlError extends Model
{
    public static string $table = 'sql_error';
    public static string $folder = 'app/config/sql-error';
    public static string $icon = 'bi bi-database-exclamation';

    public static function tableSchema(): array
    {
        return [
            Column::key('function')->type('LONGTEXT'),
            Column::key('table'),
            Column::key('query')->type('LONGTEXT'),
            Column::key('error_n')->int(),
            Column::key('error')->type('LONGTEXT'),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('function')->text()->sanitize(false),
            Field::key('table')->text(),
            Field::key('query')->text()->sanitize(false),
            Field::key('error_n')->number(),
            Field::key('error')->text()->sanitize(false),
        ];
    }
}
