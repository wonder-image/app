<?php

namespace Wonder\App\Models\Media;

use Wonder\App\Media\HasMedia;
use Wonder\App\Media\MediaFile;
use Wonder\App\Model;
use Wonder\App\RuntimeDefaults;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * I loghi del sito: stanno nella libreria Media, la riga ne tiene l'id
 * ({@see HasMedia}). Le colonne JSON di prima (`main`, `black`, ...)
 * restano, deprecate: la migrazione le svuota e si leggono solo come
 * ripiego. Il favicon `.ico` non si usa più: la colonna resta con i suoi
 * dati. L'icona app resta un file caricato come prima (si converte in png
 * e ha le sue misure fisse).
 */
final class Logo extends Model
{
    use HasMedia;

    public static string $table = 'logos';
    public static string $folder = 'app/media/logos';
    public static string $icon = 'bi bi-badge-ad';

    public static function tableSchema(): array
    {
        return [
            Column::key('slug')->unique(),
            // Deprecate: i file di prima della libreria Media.
            Column::key('main')->json(),
            Column::key('black')->json(),
            Column::key('white')->json(),
            Column::key('icon')->json(),
            Column::key('icon_black')->json(),
            Column::key('icon_white')->json(),
            // Deprecata: il favicon .ico non si usa più.
            Column::key('favicon')->json(),
            Column::key('app_icon')->json(),
            ...static::mediaTableColumns(),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('slug')->text()->required()->slug()->unique(),
            ...static::mediaDataFields(),
            # L'icona app la si esporta quasi sempre in JPG: la accettiamo e la
            # riscriviamo in PNG, così i `<link rel>` nel `<head>` e le misure
            # generate accanto all'originale hanno sempre la stessa estensione.
            Field::key('app_icon')->image()
                ->extensions(['png', 'jpg', 'jpeg'])
                ->name('{slug}-app-icon-{rand}')
                ->webp(false)
                ->convertTo('png')
                ->resize(RuntimeDefaults::appIconSizes()),
        ];
    }

    public static function mediaFields(): array
    {
        $fields = [];

        foreach (self::MEDIA as $field => [$slug, $name]) {
            $fields[$field] = [
                'columns' => ['image' => $field.'_id'],
                'slug' => '{slug}-'.$slug,
                'alt' => $name,
                'name' => $name,
                'legacy' => 'logos',
            ];
        }

        return $fields;
    }

    /** Campo => [fine dello slug, nome del media]. */
    private const MEDIA = [
        'main' => ['logo', 'Logo'],
        'black' => ['logo-black', 'Logo nero'],
        'white' => ['logo-white', 'Logo bianco'],
        'icon' => ['icon', 'Icona'],
        'icon_black' => ['icon-black', 'Icona nera'],
        'icon_white' => ['icon-white', 'Icona bianca'],
    ];

    /**
     * Gli indirizzi dei loghi di una riga, senza versione (servono a `__i()`
     * per le misure): dal media se c'è, altrimenti dal file di prima. I
     * media si leggono tutti insieme.
     *
     * @return array<string, string> campo => indirizzo, vuoto se manca
     */
    public static function urls(array $row): array
    {
        $ids = [];

        foreach (array_keys(self::MEDIA) as $field) {
            $ids[$field] = static::mediaId($row, $field);
        }

        $media = MediaFile::many(array_filter($ids));
        $urls = [];

        foreach ($ids as $field => $id) {
            $urls[$field] = $id > 0
                ? (string) ($media[$id] ?? null)?->path()
                : static::legacyMediaUrl($row, $field);
        }

        return $urls;
    }
}
