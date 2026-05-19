<?php

namespace Wonder\App\Models\Communications;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class Popup extends Model
{
    public static string $table = 'popup';
    public static string $folder = 'popups';
    public static string $icon = 'bi bi-window-stack';

    public static function tableSchema(): array
    {
        return [
            Column::key('position')->int(),
            ...static::sqlColumnsFromDataSchema([
                'slug',
                'name',
                'title',
                'url',
                'url_label',
                'view',
                'visible',
            ]),
            // Liste JSON: pagine in cui mostrarlo + immagini caricate.
            Column::key('pages')->json(),
            Column::key('images')->json(),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('slug')->text()->slug(),
            Field::key('name')->text()->sanitizeFirst(),
            Field::key('title')->text(),
            Field::key('url')->text(),
            Field::key('url_label')->text(),
            Field::key('view')->text(),
            Field::key('visible')->text(),
            Field::key('pages')->json(),
            Field::key('images')
                ->image()
                ->reset()
                ->extensions(['png', 'jpg', 'jpeg'])
                ->maxSize(2)
                ->maxFile(1)
                ->dir('/images/')
                ->responsive(),
        ];
    }
}
