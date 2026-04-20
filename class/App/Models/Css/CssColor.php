<?php

namespace Wonder\App\Models\Css;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class CssColor extends Model
{
    public static string $table = 'css_color';
    public static string $folder = 'app/css/color';
    public static string $icon = 'bi bi-palette';

    public static function tableSchema(): array
    {
        return [
            Column::key('var')->unique(),
            Column::key('name'),
            Column::key('color'),
            Column::key('contrast'),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('var')->text()->required(),
            Field::key('name')->text()->required()->sanitizeFirst(),
            Field::key('color')->text()->required(),
            Field::key('contrast')->text()->required(),
        ];
    }
}
