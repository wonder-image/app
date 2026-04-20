<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class Seo extends Model
{
    public static string $table = 'seo';
    public static string $folder = 'app/config/seo';
    public static string $icon = 'bi bi-search';

    public static function tableSchema(): array
    {
        return [
            Column::key('title'),
            Column::key('description')->type('TEXT'),
            Column::key('author'),
            Column::key('copyright'),
            Column::key('creator'),
            Column::key('reply'),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('title')->text()->required(),
            Field::key('description')->text()->required(),
            Field::key('author')->text()->required(),
            Field::key('copyright')->text()->required(),
            Field::key('creator')->text()->required(),
            Field::key('reply')->text()->required(),
        ];
    }
}
