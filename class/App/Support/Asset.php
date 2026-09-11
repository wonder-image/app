<?php

namespace Wonder\App\Support;

use Wonder\App\LegacyGlobals;

/**
 * Cache busting degli asset locali (css/js/...).
 *
 * Appende `?v={filemtime}` agli URL che puntano a file sotto ROOT: quando il
 * file cambia, cambia l'URL e il browser scarica la copia nuova invece di
 * servire quella in cache. Con URL versionati la policy `.htaccess` può
 * tenere `max-age` lunghi in sicurezza.
 *
 * Per gli asset dei moduli esiste già `Wonder\App\Module\Assets`, che usa lo
 * stesso schema `?v=`.
 */
final class Asset
{
    /**
     * Appende `?v={filemtime}` a un URL che punta a un file sotto ROOT.
     *
     * Accetta URL prefissati da APP_URL oppure path relativi alla radice
     * (`/assets/...`). URL esterni, con query string o fragment già presenti,
     * con traversal o che non corrispondono a un file esistente vengono
     * restituiti invariati. Non solleva mai eccezioni.
     *
     * `$root` e `$appUrl` sono iniettabili per i test.
     */
    public static function version(string $url, ?string $root = null, ?string $appUrl = null): string
    {
        $path = self::resolve($url, $root, $appUrl);

        if ($path !== null) {
            clearstatcache(true, $path);
        }

        if ($path !== null && is_file($path)) {
            $modified = @filemtime($path);

            if ($modified !== false) {
                return $url.'?v='.(string) $modified;
            }
        }

        return $url;
    }

    /**
     * Path su disco di un asset locale, o `null` se l'URL è esterno, malformato
     * o non corrisponde a un file esistente sotto ROOT. Stessa normalizzazione
     * di `version()` (niente query/fragment, niente traversal). Utile per
     * inlinare il contenuto di un asset nell'HTML (es. i CSS dei design token,
     * per toglierli dal render-blocking). Non solleva mai eccezioni.
     *
     * `$root` e `$appUrl` sono iniettabili per i test.
     */
    public static function path(string $url, ?string $root = null, ?string $appUrl = null): ?string
    {
        $path = self::resolve($url, $root, $appUrl);

        return ($path !== null && is_file($path)) ? $path : null;
    }

    /**
     * Normalizza un URL (prefissato da APP_URL oppure root-relative `/...`) nel
     * path su disco corrispondente sotto ROOT. Ritorna `null` per URL vuoti, con
     * query string/fragment, esterni o con traversal `..`. Non verifica
     * l'esistenza del file: quello spetta al chiamante.
     */
    private static function resolve(string $url, ?string $root = null, ?string $appUrl = null): ?string
    {
        if ($url === '' || str_contains($url, '?') || str_contains($url, '#')) {
            return null;
        }

        $root = rtrim($root ?? self::root(), '/');

        if ($root === '') {
            return null;
        }

        $appUrl = rtrim($appUrl ?? (defined('APP_URL') ? (string) APP_URL : ''), '/');

        if ($appUrl !== '' && str_starts_with($url, $appUrl.'/')) {
            $relative = substr($url, strlen($appUrl));
        } elseif (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $relative = $url;
        } else {
            // Esterno o non riconducibile a ROOT.
            return null;
        }

        // Niente traversal fuori da ROOT.
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '..') {
                return null;
            }
        }

        return $root.$relative;
    }

    /**
     * URL versionato di un file dentro `assets/{ASSETS_VERSION}/`.
     *
     * Es. `Asset::url('css/main.css')` →
     * `{APP_URL}/assets/{ASSETS_VERSION}/css/main.css?v=1721035200`.
     * Se il file non esiste, l'URL viene restituito senza `?v`.
     */
    public static function url(string $file, ?string $root = null, ?string $appUrl = null, ?string $assetsVersion = null): string
    {
        $file = ltrim(str_replace('\\', '/', trim($file)), '/');

        if ($file === '') {
            return '';
        }

        $appUrl = rtrim($appUrl ?? (defined('APP_URL') ? (string) APP_URL : ''), '/');
        $assetsVersion = $assetsVersion ?? self::assetsVersion();

        return self::version($appUrl.'/assets/'.$assetsVersion.'/'.$file, $root, $appUrl);
    }

    /**
     * Versione asset del sito, funzionante anche in console dove
     * ASSETS_VERSION non è definita (richiede l'env già caricato).
     */
    private static function assetsVersion(): string
    {
        if (defined('ASSETS_VERSION')) {
            return (string) ASSETS_VERSION;
        }

        $version = trim((string) ($_ENV['ASSETS_VERSION'] ?? ''));

        return $version !== '' ? $version : 'dev';
    }

    private static function root(): string
    {
        $root = LegacyGlobals::get('ROOT', defined('ROOT') ? ROOT : '');

        return is_string($root) ? $root : '';
    }
}
