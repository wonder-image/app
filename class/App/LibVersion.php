<?php

namespace Wonder\App;

/**
 * Versione minima di `wonder-image/lib` (npm) richiesta dal framework.
 *
 * I renderer emettono markup (`data-wi-confirm`, `data-wi-save-bar`, header
 * CSRF, ...) che funziona solo se il JS della lib lo gestisce: il minimo si
 * dichiara in un solo punto, `extra.wonder.lib` nel `composer.json` del
 * pacchetto, e `php forge update` lo confronta con la versione installata nel
 * sito.
 */
final class LibVersion
{
    public const PACKAGE = 'wonder-image';

    /**
     * Minimo dichiarato in `extra.wonder.lib` (es. `^2.1.2-alpha.23`), oppure
     * null quando il `composer.json` del pacchetto non è leggibile: alcuni
     * deploy lo rimuovono, e in quel caso il controllo non si applica.
     */
    public static function minimum(?string $composerJson = null): ?string
    {
        $path = $composerJson ?? dirname(__DIR__, 2).'/composer.json';

        if (!is_file($path)) {
            return null;
        }

        $composer = json_decode((string) file_get_contents($path), true);
        $constraint = is_array($composer) ? ($composer['extra']['wonder']['lib'] ?? null) : null;

        return is_string($constraint) ? self::version(ltrim(trim($constraint), '^~>= ')) : null;
    }

    /**
     * Versione installata nel sito, oppure null quando non è determinabile
     * (deploy senza `node_modules`, package.json illeggibile).
     */
    public static function installed(string $root): ?string
    {
        $path = rtrim($root, '/').'/node_modules/'.self::PACKAGE.'/package.json';

        if (!is_file($path)) {
            return null;
        }

        $package = json_decode((string) file_get_contents($path), true);
        $version = is_array($package) ? ($package['version'] ?? null) : null;

        if (!is_string($version)) {
            return null;
        }

        return self::version($version);
    }

    public static function satisfies(string $installed, string $minimum): bool
    {
        return version_compare(self::comparable($installed), self::comparable($minimum), '>=');
    }

    /**
     * Null quando la versione è sufficiente oppure minimo o versione installata
     * non sono determinabili: il controllo blocca solo davanti a una lib
     * installata e più vecchia del minimo.
     *
     * @return array{installed: string, minimum: string, command: string}|null
     */
    public static function check(string $root, ?string $minimum = null): ?array
    {
        $minimum ??= self::minimum();
        $installed = self::installed($root);

        if ($minimum === null || $installed === null || self::satisfies($installed, $minimum)) {
            return null;
        }

        return [
            'installed' => $installed,
            'minimum' => $minimum,
            'command' => self::installCommand($minimum),
        ];
    }

    /**
     * Il vincolo è esplicito perché `npm install wonder-image` risolve il
     * dist-tag `latest`, che può essere più vecchio di una pre-release.
     */
    public static function installCommand(string $minimum): string
    {
        return "npm install '".self::PACKAGE.'@^'.$minimum."'";
    }

    private static function version(string $version): ?string
    {
        $version = ltrim(trim($version), 'vV');

        return preg_match('/^\d+(\.\d+){0,2}/', $version) === 1 ? $version : null;
    }

    /** I metadati di build semver (`+sha`) non contano nel confronto. */
    private static function comparable(string $version): string
    {
        return explode('+', ltrim(trim($version), 'vV'), 2)[0];
    }
}
