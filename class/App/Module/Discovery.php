<?php

namespace Wonder\App\Module;

use Wonder\App\LegacyGlobals;

final class Discovery
{
    public static function discover(): array
    {
        return array_merge(
            self::bundledPackages(),
            self::composerPackages(),
            self::localPackages()
        );
    }

    private static function bundledPackages(): array
    {
        $packageRoot = dirname(__DIR__, 3);
        $modulesRoot = $packageRoot.'/modules';

        return self::filesystemPackages($modulesRoot, 'bundled', 10);
    }

    private static function localPackages(): array
    {
        $root = self::consumerRoot();

        if ($root === null) {
            return [];
        }

        return self::filesystemPackages($root.'/modules', 'local', 30);
    }

    private static function filesystemPackages(string $modulesRoot, string $source, int $priority): array
    {
        if (!is_dir($modulesRoot)) {
            return [];
        }

        $packages = [];

        foreach (glob(rtrim($modulesRoot, '/').'/*') ?: [] as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $manifestPath = $directory.'/module.json';

            if (!is_file($manifestPath)) {
                continue;
            }

            try {
                $packages[] = Manifest::fromFile($manifestPath, $source, $priority);
            } catch (\Throwable) {
                continue;
            }
        }

        return $packages;
    }

    private static function composerPackages(): array
    {
        $root = self::consumerRoot();

        if ($root === null) {
            return [];
        }

        $installedJson = $root.'/vendor/composer/installed.json';

        if (!is_file($installedJson)) {
            return [];
        }

        $json = @file_get_contents($installedJson);
        $decoded = is_string($json) ? json_decode($json, true) : null;

        if (!is_array($decoded)) {
            return [];
        }

        $packages = $decoded['packages'] ?? $decoded;

        if (!is_array($packages)) {
            return [];
        }

        $manifests = [];
        $composerRoot = dirname($installedJson);

        foreach ($packages as $package) {
            if (!is_array($package)) {
                continue;
            }

            $installPath = trim((string) ($package['install-path'] ?? ''));

            if ($installPath === '') {
                continue;
            }

            $packageRoot = realpath($composerRoot.'/'.$installPath);

            if (!is_string($packageRoot) || $packageRoot === '') {
                continue;
            }

            $manifestRelativePath = self::composerManifestPath($package, $packageRoot);

            if ($manifestRelativePath === null) {
                continue;
            }

            $manifestPath = $packageRoot.'/'.ltrim($manifestRelativePath, '/');

            if (!is_file($manifestPath)) {
                continue;
            }

            try {
                $manifests[] = Manifest::fromFile(
                    $manifestPath,
                    'composer',
                    20,
                    is_string($package['name'] ?? null) ? $package['name'] : null
                );
            } catch (\Throwable) {
                continue;
            }
        }

        return $manifests;
    }

    private static function composerManifestPath(array $package, string $packageRoot): ?string
    {
        $extraConfig = $package['extra']['wonder']['module']
            ?? $package['extra']['wonder-module']
            ?? null;

        if ($extraConfig === true) {
            return 'module.json';
        }

        if (is_string($extraConfig) && trim($extraConfig) !== '') {
            return trim($extraConfig);
        }

        if (is_array($extraConfig) && is_string($extraConfig['manifest'] ?? null) && trim($extraConfig['manifest']) !== '') {
            return trim($extraConfig['manifest']);
        }

        $packageName = trim((string) ($package['name'] ?? ''));

        if (
            str_starts_with($packageName, 'wonder-image/')
            && is_file($packageRoot.'/module.json')
        ) {
            return 'module.json';
        }

        return null;
    }

    private static function consumerRoot(): ?string
    {
        $root = LegacyGlobals::get('ROOT', defined('ROOT') ? ROOT : '');

        if (!is_string($root) || trim($root) === '') {
            return null;
        }

        return rtrim($root, '/');
    }
}
