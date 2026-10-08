<?php

namespace Wonder\Docs;

/**
 * Gli asset d'esempio del catalogo (immagini, video) in
 * `resources/assets/docs/` del pacchetto. In un sito sono raggiunti sotto
 * `/vendor/wonder-image/app/resources/assets/docs/`, come gli altri asset del
 * pacchetto; il server autonomo serve la stessa cartella allo stesso URL.
 */
final class Assets
{
    public const WEB_PATH = '/vendor/wonder-image/app/resources/assets/docs';

    public static function path(): string
    {
        return dirname(__DIR__, 2).'/resources/assets/docs';
    }

    /** L'URL di un file d'esempio, per esempio `Assets::url('paesaggio-1.jpg')`. */
    public static function url(string $file): string
    {
        $file = ltrim(trim($file), '/');
        $base = defined('APP_URL') ? (string) APP_URL : '';

        return $base.self::WEB_PATH.'/'.$file;
    }

    /** @return string[] gli URL di tutte le immagini d'esempio, in ordine di nome */
    public static function images(): array
    {
        $files = glob(self::path().'/*.{jpg,jpeg,png,webp,svg}', GLOB_BRACE) ?: [];
        sort($files);

        return array_map(static fn (string $file): string => self::url(basename($file)), $files);
    }
}
