<?php

namespace Wonder\App\Models\Media;

use Wonder\App\Model;
use Wonder\App\Support\SyncClass;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class Media extends Model
{
    public static string $table = 'media';
    public static string $folder = 'app/media';
    public static string $icon = 'bi bi-image';

    public static function syncClass(): ?SyncClass
    {
        return SyncSchema::content();
    }

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema([
                'slug',
                'name',
                'alt',
                'type',
                'source',
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
            // Tabella della sezione da cui è stato caricato (campo media di un form); vuota dalla libreria.
            Field::key('source')->text(),
            Field::key('file')->file()->required(),
        ];
    }
}
