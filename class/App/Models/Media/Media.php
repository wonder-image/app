<?php

namespace Wonder\App\Models\Media;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class Media extends Model
{
    public static string $table = 'media';
    public static string $folder = 'app/media';
    public static string $icon = 'bi bi-image';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema([
                'slug',
                'name',
                'alt',
                'type',
            ]),
            Column::key('file')->json(),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('slug')->text()->required()->slug()->unique(),
            Field::key('name')->text()->required()->sanitizeFirst(),
            Field::key('alt')->text(),
            Field::key('type')->text()->required(),
            Field::key('file')->text()->required(),
        ];
    }
}
