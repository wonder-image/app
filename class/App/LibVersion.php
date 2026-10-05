<?php

namespace Wonder\App;

/**
 * Versione minima di `wonder-image/lib` (npm) richiesta dal framework.
 *
 * I renderer emettono markup (`data-wi-confirm`, `data-wi-save-bar`, header
 * CSRF, ...) che funziona solo se il JS della lib lo gestisce: il minimo si
 * dichiara qui, in un solo punto, e `php forge update` lo confronta con la
 * versione installata nel sito.
 */
final class LibVersion
{
    public const PACKAGE = 'wonder-image';

    /** Alza questo valore quando il framework inizia a dipendere da una novità della lib. */
    public const MINIMUM = '2.1.2-alpha.23';

    public static function minimum(): string
    {
        return self::MINIMUM;
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

        $version = ltrim(trim($version), 'vV');

        return preg_match('/^\d+(\.\d+){0,2}/', $version) === 1 ? $version : null;
    }

    public static function satisfies(string $installed, ?string $minimum = null): bool
    {
        return version_compare(
            self::comparable($installed),
            self::comparable($minimum ?? self::MINIMUM),
            '>='
        );
    }

    /**
     * Null quando la versione è sufficiente o non determinabile: il controllo
     * blocca solo davanti a una lib installata e più vecchia del minimo.
     *
     * @return array{installed: string, minimum: string, command: string}|null
     */
    public static function check(string $root, ?string $minimum = null): ?array
    {
        $minimum ??= self::MINIMUM;
        $installed = self::installed($root);

        if ($installed === null || self::satisfies($installed, $minimum)) {
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
    public static function installCommand(?string $minimum = null): string
    {
        return "npm install '".self::PACKAGE.'@^'.($minimum ?? self::MINIMUM)."'";
    }

    /** I metadati di build semver (`+sha`) non contano nel confronto. */
    private static function comparable(string $version): string
    {
        return explode('+', ltrim(trim($version), 'vV'), 2)[0];
    }
}
