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

                    $config = array_replace_recursive($config, $loaded);
                }
            }

            $config = array_replace_recursive($config, StateRepository::config($slug));
            $configs[$slug] = $config;
        }

        self::$configs = $configs;

        return self::$configs;
    }

    public static function for(string $slug): array
    {
        return self::all()[trim($slug)] ?? [];
    }
}
