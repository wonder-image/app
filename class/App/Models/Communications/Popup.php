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
                ->resize([
                    ['width' => 120, 'height' => 120],
                    ['width' => 480, 'height' => 480],
                    ['width' => 620, 'height' => 620],
                    ['width' => 960, 'height' => 960],
                    ['width' => 1080, 'height' => 1080],
                    ['width' => 1440, 'height' => 1440],
                    ['width' => 1920, 'height' => 1920],
                ]),
        ];
    }
}
