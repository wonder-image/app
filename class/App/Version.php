<?php

namespace Wonder\App;

use Composer\InstalledVersions;

/**
 * Resolves the framework version from Composer metadata, so it is only
 * maintained in composer.json ("version") and bumped by `composer release`.
 */
final class Version
{
    public const PACKAGE = 'wonder-image/app';
    public const FALLBACK = 'dev';

    private static ?string $version = null;

    public static function get(): string
    {
        return self::$version ??= self::resolve();
    }

    /** Commit hash of the installed package, when Composer knows it. */
    public static function reference(int $length = 7): ?string
    {
        $reference = self::installed('getReference');

        return $reference !== null && $reference !== '' ? substr($reference, 0, $length) : null;
    }

    /** Human label, e.g. "2.3.0" or "2.3.0 (dev-main@60c6b7d)". */
    public static function label(): string
    {
        $version = self::get();
        $pretty = self::installed('getPrettyVersion');

        if ($pretty === null || !self::isDevVersion($pretty)) {
            return $version;
        }

        $reference = self::reference();

        return $version.' ('.$pretty.($reference !== null ? '@'.$reference : '').')';
    }

    private static function resolve(): string
    {
        // The shipped manifest always matches the code on disk; Composer
        // metadata can be stale and reports only "dev-main" for branch installs.
        $manifest = dirname(__DIR__, 2).'/composer.json';

        if (is_file($manifest)) {
            $data = json_decode((string) file_get_contents($manifest), true);

            if (is_array($data) && is_string($data['version'] ?? null) && $data['version'] !== '') {
                return self::normalize($data['version']);
            }
        }

        // Some deploys strip composer.json from installed packages.
        $pretty = self::installed('getPrettyVersion');

        return $pretty !== null && $pretty !== '' ? self::normalize($pretty) : self::FALLBACK;
    }

    private static function installed(string $method): ?string
    {
        if (!class_exists(InstalledVersions::class)) {
            return null;
        }

        try {
            if (!InstalledVersions::isInstalled(self::PACKAGE)) {
                return null;
            }

            $value = InstalledVersions::$method(self::PACKAGE);
        } catch (\Throwable) {
            return null;
        }

        return is_string($value) ? $value : null;
    }

    private static function isDevVersion(string $version): bool
    {
        return str_starts_with($version, 'dev-') || str_ends_with($version, '-dev');
    }

    private static function normalize(string $version): string
    {
        return ltrim(trim($version), 'vV.');
    }
}
