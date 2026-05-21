<?php

namespace Wonder\App\Module;

use RuntimeException;
use Wonder\App\Permission\PermissionRegistry;

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

    /**
     * Discovery delle cartelle `ai/agents/` dei moduli abilitati.
     *
     * Shape coerente con `resourceDirectories()`: ogni entry è un array
     * `['path' => str, 'priority' => 20, 'source' => 'module:<slug>']`.
     * Priority 20 si colloca tra framework (10) e consumer (30) nella
     * cascade di `Wonder\AI\AgentRegistry`.
     *
     * Solo i moduli che hanno dichiarato la sezione `ai` nel `module.json`
     * (con eventualmente override dei path di default) contribuiscono qui.
     */
    public static function aiAgentDirectories(): array
    {
        return self::aiDirectories('aiAgentsPath');
    }

    /**
     * Discovery delle cartelle `ai/prompts/` dei moduli abilitati.
     * Vedi `aiAgentDirectories()`.
     */
    public static function aiPromptDirectories(): array
    {
        return self::aiDirectories('aiPromptsPath');
    }

    /**
     * Discovery delle cartelle `ai/tools/` dei moduli abilitati.
     * Vedi `aiAgentDirectories()`.
     */
    public static function aiToolDirectories(): array
    {
        return self::aiDirectories('aiToolsPath');
    }

    /**
     * Helper interno: itera i moduli enabled() e raccoglie un path-getter
     * con la shape standard ['path', 'priority', 'source'].
     *
     * Volutamente NON public: i 3 metodi sopra (`aiAgentDirectories` ecc.)
     * sono i punti di estensione stabili. Aggiungere un nuovo tipo di
     * risorsa AI = aggiungere un nuovo wrapper + un nuovo path-getter
     * su `Manifest`.
     */
    private static function aiDirectories(string $manifestMethod): array
    {
        $directories = [];

        foreach (self::enabled() as $manifest) {
            $path = $manifest->{$manifestMethod}();

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

    public static function mergePermissions(array|PermissionRegistry $permits): array
    {
        
        $registry = PermissionRegistry::from($permits);

        foreach (self::enabled() as $manifest) {
            $file = $manifest->permissionsFile();

            if ($file === null || !is_file($file)) {
                continue;
            }

            try {
                $registry->merge(PermissionRegistry::fromFile($file));
            } catch (\Throwable $throwable) {
                throw new RuntimeException(
                    'Il file permissions del modulo '.$manifest->slug().' non e\' valido: '.$throwable->getMessage(),
                    previous: $throwable
                );
            }
        }

        return $registry->toArray();
    }
}
