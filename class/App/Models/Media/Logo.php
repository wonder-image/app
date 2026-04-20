<?php

namespace Wonder\App\Models\Media;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class Logo extends Model
{
    public static string $table = 'logos';
    public static string $folder = 'app/media/logos';
    public static string $icon = 'bi bi-badge-ad';

    public static function tableSchema(): array
    {
        return [
            Column::key('slug')->unique(),
            Column::key('main')->json(),
            Column::key('black')->json(),
            Column::key('white')->json(),
            Column::key('icon')->json(),
            Column::key('icon_black')->json(),
            Column::key('icon_white')->json(),
            Column::key('favicon')->json(),
            Column::key('app_icon')->json(),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('slug')->text()->required()->slug()->unique(),
            Field::key('main')->text(),
            Field::key('black')->text(),
            Field::key('white')->text(),
            Field::key('icon')->text(),
            Field::key('icon_black')->text(),
            Field::key('icon_white')->text(),
            Field::key('favicon')->text(),
            Field::key('app_icon')->text(),
        ];
    }
}
