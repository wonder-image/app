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
            self::vendorPackages(),
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

    private static function vendorPackages(): array
    {
        $root = self::consumerRoot();

        if ($root === null) {
            return [];
        }

        return self::filesystemPackages($root.'/vendor/wonder-image', 'vendor', 18);
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

        $composerRoot = $root.'/vendor/composer';
        $packages = self::composerInstalledPackages($composerRoot);

        if ($packages === []) {
            return [];
        }

        $manifests = [];

        foreach ($packages as $package) {
            if (!is_array($package)) {
                continue;
            }

            $installPath = trim((string) ($package['install-path'] ?? $package['install_path'] ?? ''));

            if ($installPath === '') {
                continue;
            }

            $candidateRoot = str_starts_with($installPath, '/')
                ? $installPath
                : $composerRoot.'/'.$installPath;
            $packageRoot = realpath($candidateRoot);

            if (!is_string($packageRoot) || $packageRoot === '') {
                continue;
            }

            $manifestRelativePath = self::composerManifestPath($package, $packageRoot);

            if ($manifestRelativePath !== null) {
                $manifestPath = $packageRoot.'/'.ltrim($manifestRelativePath, '/');

                if (is_file($manifestPath)) {
                    try {
                        $manifests[] = Manifest::fromFile(
                            $manifestPath,
                            'composer',
                            20,
                            is_string($package['name'] ?? null) ? $package['name'] : null
                        );
                        continue;
                    } catch (\Throwable) {
                    }
                }
            }

            $legacyManifest = self::legacyComposerManifest($package, $packageRoot);

            if ($legacyManifest !== null) {
                $manifests[] = $legacyManifest;
            }
        }

        return $manifests;
    }

    private static function composerInstalledPackages(string $composerRoot): array
    {
        $packages = [];

        foreach (self::packagesFromInstalledPhp($composerRoot.'/installed.php') as $package) {
            $name = trim((string) ($package['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $packages[$name] = $package;
        }

        foreach (self::packagesFromInstalledJson($composerRoot.'/installed.json') as $package) {
            $name = trim((string) ($package['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            if (!isset($packages[$name])) {
                $packages[$name] = $package;
                continue;
            }

            $packages[$name] = array_replace($package, $packages[$name]);

            foreach ($package as $key => $value) {
                if (!array_key_exists($key, $packages[$name]) || $packages[$name][$key] === null || $packages[$name][$key] === '') {
                    $packages[$name][$key] = $value;
                }
            }
        }

        return array_values($packages);
    }

    private static function packagesFromInstalledPhp(string $installedPhp): array
    {
        if (!is_file($installedPhp)) {
            return [];
        }

        $data = require $installedPhp;

        if (!is_array($data)) {
            return [];
        }

        $versions = $data['versions'] ?? [];

        if (!is_array($versions)) {
            return [];
        }

        $packages = [];

        foreach ($versions as $name => $package) {
            if (!is_string($name) || !is_array($package)) {
                continue;
            }

            $package['name'] = $name;
            $package['install-path'] = $package['install-path'] ?? $package['install_path'] ?? '';
            $package['version'] = $package['version'] ?? $package['pretty_version'] ?? '0.0.0';
            $packages[] = $package;
        }

        return $packages;
    }

    private static function packagesFromInstalledJson(string $installedJson): array
    {
        if (!is_file($installedJson)) {
            return [];
        }

        $json = @file_get_contents($installedJson);
        $decoded = is_string($json) ? json_decode($json, true) : null;

        if (!is_array($decoded)) {
            return [];
        }

        $packages = $decoded['packages'] ?? $decoded;

        return is_array($packages) ? $packages : [];
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

    private static function legacyComposerManifest(array $package, string $packageRoot): ?Manifest
    {
        $packageName = trim((string) ($package['name'] ?? ''));

        if (!str_starts_with($packageName, 'wonder-image/')) {
            return null;
        }

        $autoload = $package['autoload']['psr-4'] ?? [];

        if (!is_array($autoload) || $autoload === []) {
            return null;
        }

        $namespace = '';

        foreach (array_keys($autoload) as $prefix) {
            if (is_string($prefix) && str_starts_with($prefix, 'Wonder\\Plugin\\')) {
                $namespace = $prefix;
                break;
            }
        }

        if ($namespace === '') {
            return null;
        }

        $slug = trim(substr($packageName, strlen('wonder-image/')));

        if ($slug === '') {
            return null;
        }

        $studly = self::studly($slug);
        $entrypoint = rtrim($namespace, '\\').'\\'.$studly;

        $data = [
            'name' => (string) ($package['description'] ?? $studly),
            'slug' => $slug,
            'version' => self::normalizeVersion((string) ($package['version'] ?? '0.0.0')),
            'description' => (string) ($package['description'] ?? $studly),
            'namespace' => $namespace,
            'entrypoint' => $entrypoint,
            'author' => [
                'name' => 'Legacy Composer Module',
            ],
            'frameworkCompatibility' => [
                'wonder-app' => (string) ($package['require']['wonder-image/app'] ?? '*'),
                'php' => (string) ($package['require']['php'] ?? '^8.2'),
            ],
            'dependencies' => [
                'modules' => [],
                'composer' => [],
            ],
            'paths' => [
                'src' => 'src',
                'handlers' => 'handlers',
                'views' => 'views',
                'assets' => 'resources/assets',
                'lang' => 'lang',
                'tests' => 'tests',
            ],
            'routes' => [
                'frontend' => 'integrations/new-site/custom/routes/route.'.$slug.'.frontend.php',
                'backend' => 'integrations/new-site/custom/routes/route.'.$slug.'.backend.php',
                'api' => 'integrations/new-site/custom/routes/route.'.$slug.'.api.php',
            ],
            'permissions' => [
                'definitions' => 'config/permissions.php',
            ],
            'database' => [
                'models' => 'src/Models',
                'update' => 'build/update',
                'row' => 'build/row',
                'install' => 'build/install.php',
                'uninstall' => 'build/uninstall.php',
            ],
            'boot' => [
                'files' => [
                    'integrations/new-site/custom/config/config.php',
                    'integrations/new-site/custom/config/consent.php',
                ],
            ],
            'legacy' => true,
        ];

        return Manifest::fromArray(
            $packageRoot,
            $packageRoot.'/module.json',
            $data,
            'composer-legacy',
            15,
            $packageName
        );
    }

    private static function normalizeVersion(string $version): string
    {
        if (preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', $version)) {
            return $version;
        }

        return '0.0.0';
    }

    private static function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', trim($value));
        $value = ucwords($value);

        return str_replace(' ', '', $value);
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
