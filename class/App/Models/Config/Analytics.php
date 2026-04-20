<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class Analytics extends Model
{
    public static string $table = 'analytics';
    public static string $folder = 'app/config/analytics';
    public static string $icon = 'bi bi-graph-up';

    public static function tableSchema(): array
    {
        return [
            Column::key('tag_manager'),
            Column::key('active_tag_manager')->default('false'),
            Column::key('pixel_facebook'),
            Column::key('active_pixel_facebook')->default('false'),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('tag_manager')->text(),
            Field::key('active_tag_manager')->text()->required(),
            Field::key('pixel_facebook')->text(),
            Field::key('active_pixel_facebook')->text()->required(),
        ];
    }
}
