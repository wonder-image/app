<?php

namespace Wonder\App\Models\Css;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class CssInput extends Model
{
    public static string $table = 'css_input';
    public static string $folder = 'app/css/input';
    public static string $icon = 'bi bi-input-cursor-text';

    public static function tableSchema(): array
    {
        return [
            Column::key('tx_color'),
            Column::key('bg_color'),
            Column::key('dropdown_tx_color')->default('var(--input-tx-color)'),
            Column::key('dropdown_bg_color')->default('var(--input-bg-color)'),
            Column::key('disabled_bg_color'),
            Column::key('label_color'),
            Column::key('label_color_focus'),
            Column::key('label_weight'),
            Column::key('label_weight_focus'),
            Column::key('select_hover'),
            Column::key('border_color'),
            Column::key('border_color_focus'),
            Column::key('border_radius'),
            Column::key('border_top'),
            Column::key('border_right'),
            Column::key('border_bottom'),
            Column::key('border_left'),
            Column::key('date_default'),
            Column::key('date_active'),
            Column::key('date_bg'),
            Column::key('date_bg_hover'),
            Column::key('date_border_radius'),
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
