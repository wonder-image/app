<?php

namespace Wonder\App\Models\Css;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

final class CssFont extends Model
{
    public static string $table = 'css_font';
    public static string $folder = 'app/css/font';
    public static string $icon = 'bi bi-fonts';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema([
                'name',
                'link',
                'font_family',
                'visible',
            ]),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('name')->text()->required()->sanitizeFirst(),
            Field::key('link')->text()->required(),
            Field::key('font_family')->text()->required(),
            Field::key('visible')->text()->required(),
        ];
    }
}
