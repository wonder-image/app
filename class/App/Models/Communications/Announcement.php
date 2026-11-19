<?php

namespace Wonder\App\Models\Communications;

use Wonder\App\Model;
use Wonder\App\Support\SyncClass;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class Announcement extends Model
{
    public static string $table = 'announcements';
    public static string $folder = 'announcements';
    public static string $icon = 'bi bi-megaphone';

    public static function syncClass(): ?SyncClass
    {
        return SyncSchema::content();
    }

    public static function tableSchema(): array
    {
        return [
            Column::key('position')->int(),
            ...static::sqlColumnsFromDataSchema([
                'slug',
                'name',
                'text',
                'visible',
            ]),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('slug')->text()->slug(),
            Field::key('name')->text()->sanitizeFirst(),
            Field::key('text')->text(),
            Field::key('visible')->text(),
        ];
    }
}
