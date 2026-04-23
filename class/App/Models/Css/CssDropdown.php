<?php

namespace Wonder\App\Models\Css;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

final class CssDropdown extends Model
{
    public static string $table = 'css_dropdown';
    public static string $folder = 'app/css/dropdown';
    public static string $icon = 'bi bi-menu-button';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema([
                'tx',
                'bg',
                'bg_hover',
                'border_color',
                'border_width',
                'border_radius',
            ]),
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
