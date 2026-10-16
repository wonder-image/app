<?php

namespace Wonder\App\Module;

use RuntimeException;
use Wonder\App\LegacyGlobals;

final class ConfigRepository
{
    private static ?array $configs = null;

    public static function reset(): void
    {
        self::$configs = null;
    }

    public static function all(): array
    {
        if (self::$configs !== null) {
            return self::$configs;
        }

        $root = LegacyGlobals::get('ROOT', defined('ROOT') ? ROOT : '');
        $configs = [];

        foreach (Registry::enabled() as $slug => $manifest) {
            $config = [];

            $defaultFile = $manifest->defaultConfigFile();

            if ($defaultFile !== null && is_file($defaultFile)) {
                $loaded = require $defaultFile;

                if (!is_array($loaded)) {
                    throw new RuntimeException("Il config default del modulo {$slug} deve restituire un array.");
                }

                $config = $loaded;
            }

            if (is_string($root) && trim($root) !== '') {
                $overrideFile = $manifest->dedicatedConfigOverridePath($root);

                if (is_file($overrideFile)) {
                    $loaded = require $overrideFile;

                    if (!is_array($loaded)) {
                        throw new RuntimeException("L'override config del modulo {$slug} deve restituire un array.");
                    }

                    $config = self::merge($config, $loaded);
                }
            }

            # I permessi si decidono nei file, non dallo stato salvato in DB.
            $state = StateRepository::config($slug);
            unset($state['permissions']);

            $config = self::merge($config, $state);
            $configs[$slug] = $config;
        }

        self::$configs = $configs;

        return self::$configs;
    }

    /**
     * Unisce un override al config. Sotto `permissions` ogni area è sostituita
     * per intero: con l'unione ricorsiva un override `['admin']` sopra
     * `['admin', 'administrator']` lascerebbe `administrator`.
     */
    public static function merge(array $config, array $override): array
    {
        $permissions = null;

        if (array_key_exists('permissions', $override)) {
            $permissions = array_replace((array) ($config['permissions'] ?? []), (array) $override['permissions']);
        }

        $config = array_replace_recursive($config, $override);

        if ($permissions !== null) {
            $config['permissions'] = $permissions;
        }

        return $config;
    }

    public static function for(string $slug): array
    {
        return self::all()[trim($slug)] ?? [];
    }
}
