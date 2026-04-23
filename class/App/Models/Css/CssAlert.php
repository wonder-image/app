<?php

namespace Wonder\App\Models\Css;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

final class CssAlert extends Model
{
    public static string $table = 'css_alert';
    public static string $folder = 'app/css/alert';
    public static string $icon = 'bi bi-bell';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema([
                'tx',
                'bg',
                'top',
                'right',
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
            Field::key('top')->text()->required(),
            Field::key('right')->text()->required(),
            Field::key('border_color')->text()->required(),
            Field::key('border_width')->text()->required(),
            Field::key('border_radius')->text()->required(),
        ];
    }
}
