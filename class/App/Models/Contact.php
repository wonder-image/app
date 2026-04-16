<?php

namespace Wonder\App\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class Contact extends Model
{
    public static string $table = 'contact';
    public static string $folder = 'contact';
    public static string $icon = 'bi bi-circle';

    public static function tableSchema(): array
    {
        return [
            // Column::key('name'),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            // Field::key('name')->text()->required(),
        ];
    }
}
