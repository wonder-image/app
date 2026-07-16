<?php

namespace Wonder\App\Module;

use Wonder\App\LegacyGlobals;

/**
 * Risoluzione degli asset (css/js/img) dei moduli.
 *
 * Ordine di risoluzione dell'URL pubblico di un file:
 *  1. copia pubblicata nel sito: `assets/{ASSETS_VERSION}/<file>`
 *     (creata da `php forge publish:module <slug> --assets`, il sito può
 *     modificarla liberamente e vince sempre sul file del modulo);
 *  2. file del modulo dentro `paths.assets` (default `resources/assets`),
 *     servito direttamente dal suo percorso reale (`vendor/wonder-image/...`
 *     o `modules/<slug>/...`);
 *  3. fallback sul symlink `vendor/<package>` quando il root del modulo
 *     risolve fuori da ROOT (repository Composer di tipo path).
 *
 * Ogni URL include `?v=` per il cache busting: la versione del modulo per i
 * file del modulo, il filemtime per le copie pubblicate.
 */
final class Assets
{
    /**
     * Versione asset del sito, funzionante anche in console dove
     * ASSETS_VERSION non è definita (richiede l'env già caricato).
     */
    public static function version(): string
    {
        if (defined('ASSETS_VERSION')) {
            return (string) ASSETS_VERSION;
        }

        $version = trim((string) ($_ENV['ASSETS_VERSION'] ?? ''));

        return $version !== '' ? $version : 'dev';
    }

    /**
     * Directory del sito in cui `publish:module --assets` copia gli asset.
     */
    public static function publishTarget(string $root): string
    {
        return rtrim($root, '/').'/assets/'.self::version();
    }

    /**
     * URL pubblico di un asset del modulo `$slug`, stringa vuota se il file
     * non esiste o il modulo non è registrato.
     */
    public static function url(string $slug, string $file): string
    {
        try {
            $manifest = Registry::get($slug);
        } catch (\Throwable) {
            return '';
        }

        return self::urlFor($manifest, $file);
    }

    /**
     * Come `url()`, ma a partire da un Manifest già risolto. `$root`,
     * `$appUrl` e `$assetsVersion` sono iniettabili per i test.
     */
    public static function urlFor(
        Manifest $manifest,
        string $file,
        ?string $root = null,
        ?string $appUrl = null,
        ?string $assetsVersion = null,
    ): string {
        $file = self::sanitizeFile($file);

        if ($file === null) {
            return '';
        }

        $root = rtrim($root ?? self::root(), '/');
        $appUrl = rtrim($appUrl ?? (defined('APP_URL') ? (string) APP_URL : ''), '/');
        $assetsVersion = $assetsVersion ?? self::version();

        if ($root === '') {
            return '';
        }

        // 1. Copia pubblicata nel sito (override, sempre prioritaria).
        $published = $root.'/assets/'.$assetsVersion.'/'.$file;

        if (is_file($published)) {
            return $appUrl.'/assets/'.$assetsVersion.'/'.$file.'?v='.(string) filemtime($published);
        }

        $assetsPath = $manifest->assetsPath();

        if ($assetsPath === null) {
            return '';
        }

        $sourceFile = rtrim($assetsPath, '/').'/'.$file;

        if (!is_file($sourceFile)) {
            return '';
        }

        $version = $manifest->version() !== '' ? $manifest->version() : (string) filemtime($sourceFile);

        // 2. File del modulo raggiungibile sotto ROOT (vendor/, modules/, bundled).
        if (str_starts_with($sourceFile, $root.'/')) {
            return $appUrl.substr($sourceFile, strlen($root)).'?v='.$version;
        }

        // 3. Root del modulo fuori da ROOT (repository path con symlink in
        //    vendor/): il file resta raggiungibile via vendor/<package>.
        $package = trim((string) $manifest->composerPackage());
        $moduleRoot = rtrim($manifest->root(), '/');

        if ($package !== '' && str_starts_with($sourceFile, $moduleRoot.'/')) {
            $relative = substr($sourceFile, strlen($moduleRoot) + 1);
            $candidate = $root.'/vendor/'.$package.'/'.$relative;

            if (is_file($candidate)) {
                return $appUrl.'/vendor/'.$package.'/'.$relative.'?v='.$version;
            }
        }

        return '';
    }

    private static function root(): string
    {
        $root = LegacyGlobals::get('ROOT', defined('ROOT') ? ROOT : '');

        return is_string($root) ? $root : '';
    }

    /**
     * Normalizza il path relativo dell'asset e rifiuta i traversal.
     */
    private static function sanitizeFile(string $file): ?string
    {
        $file = trim(str_replace('\\', '/', $file), " \t\n\r\0/");

        if ($file === '') {
            return null;
        }

        foreach (explode('/', $file) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $file;
    }
}
