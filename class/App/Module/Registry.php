<?php

namespace Wonder\App\Module;

use RuntimeException;

final class Registry
{
    private static ?array $modules = null;
    private static ?array $invalidModules = null;

    public static function reset(): void
    {
        self::$modules = null;
        self::$invalidModules = null;
        StateRepository::reset();
        ConfigRepository::reset();
    }

    public static function all(): array
    {
        if (self::$modules !== null) {
            return self::$modules;
        }

        $modules = [];
        $invalidModules = [];
        $priorities = [];

        foreach (Discovery::discover() as $manifest) {
            if (!$manifest instanceof Manifest) {
                continue;
            }

            try {
                ManifestValidator::assertValid($manifest);
            } catch (\Throwable $throwable) {
                $slug = $manifest->slug();

                if ($slug !== '') {
                    $invalidModules[$slug] = $throwable->getMessage();
                }
                continue;
            }

            $slug = $manifest->slug();
            $priority = $manifest->priority();

            if (isset($modules[$slug])) {
                $currentPriority = (int) ($priorities[$slug] ?? 0);

                if ($priority === $currentPriority && $modules[$slug]->root() !== $manifest->root()) {
                    throw new RuntimeException('Slug modulo duplicato: '.$slug);
                }

                if ($priority < $currentPriority) {
                    continue;
                }
            }

            $modules[$slug] = $manifest;
            $priorities[$slug] = $priority;
        }

        ksort($modules);
        self::$modules = $modules;
        self::$invalidModules = $invalidModules;

        return self::$modules;
    }

    public static function invalidReasons(): array
    {
        if (self::$invalidModules === null) {
            self::all();
        }

        return self::$invalidModules ?? [];
    }

    public static function enabled(): array
    {
        $enabled = [];
        $available = self::all();
        $invalid = self::invalidReasons();

        foreach (StateRepository::all() as $slug => $state) {
            if (($state['enabled'] ?? false) !== true) {
                continue;
            }

            if (!isset($available[$slug])) {
                if (isset($invalid[$slug]) && trim((string) $invalid[$slug]) !== '') {
                    throw new RuntimeException('Modulo abilitato ma non valido: '.$slug.' | '.$invalid[$slug]);
                }

                throw new RuntimeException('Modulo abilitato ma non disponibile: '.$slug);
            }

            foreach ($available[$slug]->dependencySlugs() as $dependencySlug) {
                if (!StateRepository::isEnabled($dependencySlug)) {
                    throw new RuntimeException("Il modulo {$slug} richiede {$dependencySlug} abilitato.");
                }
            }

            $enabled[$slug] = $available[$slug];
        }

        return $enabled;
    }

    public static function has(string $slug): bool
    {
        return isset(self::all()[trim($slug)]);
    }

    public static function get(string $slug): Manifest
    {
        $slug = trim($slug);

        if (!isset(self::all()[$slug])) {
            throw new RuntimeException('Modulo non registrato: '.$slug);
        }

        return self::all()[$slug];
    }

    public static function modelDirectories(): array
    {
        $directories = [];

        foreach (self::enabled() as $manifest) {
            $path = $manifest->modelsPath();

            if ($path !== null && is_dir($path)) {
                $directories[] = $path;
            }
        }

        return $directories;
    }

    public static function resourceDirectories(): array
    {
        $directories = [];

        foreach (self::enabled() as $manifest) {
            $path = $manifest->resourcesPath();

            if ($path === null || !is_dir($path)) {
                continue;
            }

            $directories[] = [
                'path' => $path,
                'priority' => 20,
                'source' => 'module:'.$manifest->slug(),
            ];
        }

        return $directories;
    }

    public static function routeFiles(string $area): array
    {
        $files = [];

        foreach (self::enabled() as $manifest) {
            $path = $manifest->routeFile($area);

            if ($path !== null && is_file($path)) {
                $files[] = $path;
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }

    public static function languagePaths(): array
    {
        $paths = [];

        foreach (self::enabled() as $manifest) {
            $path = $manifest->langPath();

            if ($path !== null && is_dir($path)) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    public static function bootFiles(): array
    {
        $files = [];

        foreach (self::enabled() as $manifest) {
            foreach ($manifest->bootFiles() as $file) {
                if (is_file($file)) {
                    $files[] = $file;
                }
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }

    public static function mergePermissions(array $permits): array
    {
        foreach (self::enabled() as $manifest) {
            $file = $manifest->permissionsFile();

            if ($file === null || !is_file($file)) {
                continue;
            }

            $loaded = require $file;

            if (!is_array($loaded)) {
                throw new RuntimeException('Il file permissions del modulo '.$manifest->slug().' deve restituire un array.');
            }

            foreach ($loaded as $area => $definitions) {
                if (!isset($permits[$area]) || !is_array($permits[$area])) {
                    $permits[$area] = [];
                }

                if (!is_array($definitions)) {
                    continue;
                }

                $permits[$area] = array_replace_recursive($permits[$area], $definitions);
            }
        }

        return $permits;
    }
}
