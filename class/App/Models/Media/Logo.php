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
            Field::key('main')->image()
                ->extensions(['png'])
                ->name('{slug}-logo-{rand}')
                ->webp(RESPONSIVE_IMAGE_WEBP)
                ->resize(RESPONSIVE_IMAGE_SIZES),
            Field::key('black')->image()
                ->extensions(['png'])
                ->name('{slug}-logo-black-{rand}')
                ->webp(RESPONSIVE_IMAGE_WEBP)
                ->resize(RESPONSIVE_IMAGE_SIZES),
            Field::key('white')->image()
                ->extensions(['png'])
                ->name('{slug}-logo-white-{rand}')
                ->webp(RESPONSIVE_IMAGE_WEBP)
                ->resize(RESPONSIVE_IMAGE_SIZES),
            Field::key('icon')->image()
                ->extensions(['png'])
                ->name('{slug}-icon-{rand}')
                ->webp(RESPONSIVE_IMAGE_WEBP)
                ->resize(RESPONSIVE_IMAGE_SIZES),
            Field::key('icon_black')->image()
                ->extensions(['png'])
                ->name('{slug}-icon-black-{rand}')
                ->webp(RESPONSIVE_IMAGE_WEBP)
                ->resize(RESPONSIVE_IMAGE_SIZES),
            Field::key('icon_white')->image()
                ->extensions(['png'])
                ->name('{slug}-icon-white-{rand}')
                ->webp(RESPONSIVE_IMAGE_WEBP)
                ->resize(RESPONSIVE_IMAGE_SIZES),
            Field::key('favicon')->file()
                ->extensions(['ico'])
                ->maxSize(1)
                ->name('favicon')
                ->dir('/../../../favicon'),
            Field::key('app_icon')->image()
                ->extensions(['png'])
                ->name('{slug}-app-icon-{rand}')
                ->webp(false)
                ->resize($GLOBALS['DEFAULT']->appIcon ?? []),
        ];
    }
}
