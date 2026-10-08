<?php

namespace Wonder\View;

use Wonder\App\Support\CssFontFamily;

/**
 * Catalogo chiuso dei font web serviti da `resources/assets/font/web/`.
 * Il gestionale salva la chiave (`inter`, `open-sans`, …); una chiave vuota
 * o sconosciuta vale «come il sito» e non produce CSS.
 */
final class WebFonts
{
    /**
     * chiave => [nome, cartella, [peso CSS => file]]. I font variable hanno
     * un solo file con l'intervallo di pesi dell'asse `wght`.
     */
    private const FONTS = [
        'inter' => ['Inter', 'Inter', ['100 900' => 'inter-latin-wght-normal.woff2']],
        'roboto' => ['Roboto', 'Roboto', ['100 900' => 'roboto-latin-wght-normal.woff2']],
        'open-sans' => ['Open Sans', 'OpenSans', ['300 800' => 'open-sans-latin-wght-normal.woff2']],
        'lato' => ['Lato', 'Lato', ['400' => 'lato-latin-400-normal.woff2', '700' => 'lato-latin-700-normal.woff2']],
        'montserrat' => ['Montserrat', 'Montserrat', ['100 900' => 'montserrat-latin-wght-normal.woff2']],
        'poppins' => ['Poppins', 'Poppins', [
            '400' => 'poppins-latin-400-normal.woff2',
            '500' => 'poppins-latin-500-normal.woff2',
            '600' => 'poppins-latin-600-normal.woff2',
            '700' => 'poppins-latin-700-normal.woff2',
        ]],
        'dm-sans' => ['DM Sans', 'DMSans', ['100 1000' => 'dm-sans-latin-wght-normal.woff2']],
        'nunito' => ['Nunito', 'Nunito', ['200 1000' => 'nunito-latin-wght-normal.woff2']],
        'work-sans' => ['Work Sans', 'WorkSans', ['100 900' => 'work-sans-latin-wght-normal.woff2']],
    ];

    /** Variabili del sito che `css()` ridefinisce (vedi `assets/<v>/css/set-up/root.css`). */
    private const VARIABLES = [
        '--font-family',
        '--title-big-font-family',
        '--title-font-family',
        '--subtitle-font-family',
        '--text-font-family',
        '--text-small-font-family',
    ];

    /** @return array<string, string> chiave => nome */
    public static function all(): array
    {
        return array_map(static fn (array $font): string => $font[0], self::FONTS);
    }

    public static function has(string $key): bool
    {
        return isset(self::FONTS[self::key($key)]);
    }

    /** @return array<int, string> percorsi assoluti dei file woff2 */
    public static function files(string $key): array
    {
        $font = self::FONTS[self::key($key)] ?? null;

        if ($font === null) {
            return [];
        }

        $dir = dirname(__DIR__, 2).'/resources/assets/font/web/'.$font[1];

        return array_values(array_map(static fn (string $file): string => $dir.'/'.$file, $font[2]));
    }

    /**
     * `@font-face` e variabili del sito per il font `$key`, senza `<style>`.
     * Stringa vuota per una chiave sconosciuta o senza URL base.
     */
    public static function css(string $key, ?string $baseUrl = null): string
    {
        $font = self::FONTS[self::key($key)] ?? null;
        $baseUrl = rtrim(str_replace(['"', '<', '>'], '', $baseUrl ?? self::baseUrl()), '/');

        if ($font === null || $baseUrl === '') {
            return '';
        }

        [$name, $dir, $files] = $font;
        $css = '';

        foreach ($files as $weight => $file) {
            $css .= '@font-face{font-family:"'.$name.'";font-style:normal;font-display:swap;'
                .'font-weight:'.$weight.';'
                .'src:url("'.$baseUrl.'/'.$dir.'/'.$file.'") format("woff2");}';
        }

        return $css.self::variables('"'.$name.'", sans-serif');
    }

    /**
     * Le variabili del sito con la famiglia di una riga di `css_font`, senza
     * `<style>`: il font lo carica già la testata. Vuota per una famiglia vuota.
     */
    public static function variables(string $fontFamily): string
    {
        $stack = str_replace(['<', '>', '{', '}', ';'], '', CssFontFamily::normalize($fontFamily));

        if (trim($stack) === '') {
            return '';
        }

        return 'html:root{'.implode('', array_map(
            static fn (string $variable): string => $variable.':'.$stack.';',
            self::VARIABLES,
        )).'}';
    }

    private static function key(string $key): string
    {
        return strtolower(trim($key));
    }

    /** URL della cartella dei font, dallo stesso `$PATH->appAssets` dei loghi. */
    private static function baseUrl(): string
    {
        $path = $GLOBALS['PATH'] ?? null;
        $assets = is_object($path) ? (string) ($path->appAssets ?? '') : '';

        return $assets !== '' ? $assets.'/font/web' : '';
    }
}
