<?php

namespace Wonder\App\Models\Css;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class CssDefault extends Model
{
    public static string $table = 'css_default';
    public static string $folder = 'app/css/default';
    public static string $icon = 'bi bi-type';

    public static function tableSchema(): array
    {
        return [
            Column::key('font_id')->int()->foreign('css_font'),
            Column::key('font_weight'),
            Column::key('font_size'),
            Column::key('line_height'),
            Column::key('title_big_font_id')->int()->foreign('css_font'),
            Column::key('title_big_font_weight'),
            Column::key('title_big_font_size'),
            Column::key('title_big_line_height'),
            Column::key('title_font_id')->int()->foreign('css_font'),
            Column::key('title_font_weight'),
            Column::key('title_font_size'),
            Column::key('title_line_height'),
            Column::key('subtitle_font_id')->int()->foreign('css_font'),
            Column::key('subtitle_font_weight'),
            Column::key('subtitle_font_size'),
            Column::key('subtitle_line_height'),
            Column::key('text_font_id')->int()->foreign('css_font'),
            Column::key('text_font_weight'),
            Column::key('text_font_size'),
            Column::key('text_line_height'),
            Column::key('text_small_font_id')->int()->foreign('css_font'),
            Column::key('text_small_font_weight'),
            Column::key('text_small_font_size'),
            Column::key('text_small_line_height'),
            Column::key('button_font_size'),
            Column::key('button_line_height'),
            Column::key('button_font_weight'),
            Column::key('button_border_radius'),
            Column::key('button_border_width'),
            Column::key('badge_font_size'),
            Column::key('badge_line_height'),
            Column::key('badge_font_weight'),
            Column::key('badge_border_radius'),
            Column::key('badge_border_width'),
            Column::key('tx_color'),
            Column::key('bg_color'),
            Column::key('spacer'),
            Column::key('header_height')->default('80'),
        ];
    }

    public static function dataSchema(): array
    {
        $fields = [];

        foreach (array_keys(static::getColumns()) as $column) {
            $fields[] = Field::key($column)->text()->required();
        }

        return $fields;
    }
}
