<?php

namespace Wonder\App\Models\Css;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class CssDropdown extends Model
{
    public static string $table = 'css_dropdown';
    public static string $folder = 'app/css/dropdown';
    public static string $icon = 'bi bi-menu-button';

    public static function tableSchema(): array
    {
        return [
            Column::key('tx'),
            Column::key('bg'),
            Column::key('bg_hover'),
            Column::key('border_color'),
            Column::key('border_width'),
            Column::key('border_radius'),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('tx')->text()->required(),
            Field::key('bg')->text()->required(),
            Field::key('bg_hover')->text()->required(),
            Field::key('border_color')->text()->required(),
            Field::key('border_width')->text()->required(),
            Field::key('border_radius')->text()->required(),
        ];
    }
}
