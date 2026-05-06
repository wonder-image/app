<?php

namespace Wonder\App\Module;

use RuntimeException;
use Wonder\App\LegacyGlobals;

final class StateRepository
{
    private static ?array $state = null;

    public static function reset(): void
    {
        self::$state = null;
    }

    public static function all(): array
    {
        if (self::$state !== null) {
            return self::$state;
        }

        $file = self::stateFile();

        if ($file === null || !is_file($file)) {
            self::$state = [];
            return self::$state;
        }

        $loaded = require $file;

        if (!is_array($loaded)) {
            throw new RuntimeException('custom/config/modules.php deve restituire un array.');
        }

        $state = [];

        foreach ($loaded as $slug => $definition) {
            if (!is_string($slug) || trim($slug) === '') {
                continue;
            }

            $slug = trim($slug);

            if (is_bool($definition)) {
                $state[$slug] = [
                    'enabled' => $definition,
                    'config' => [],
                ];
                continue;
            }

            if (!is_array($definition)) {
                continue;
            }

            $state[$slug] = [
                'enabled' => (bool) ($definition['enabled'] ?? false),
                'config' => is_array($definition['config'] ?? null) ? $definition['config'] : [],
            ];
        }

        self::$state = $state;

        return self::$state;
    }

    public static function isEnabled(string $slug): bool
    {
        return (bool) (self::all()[trim($slug)]['enabled'] ?? false);
    }

    public static function config(string $slug): array
    {
        $definition = self::all()[trim($slug)] ?? [];

        return is_array($definition['config'] ?? null) ? $definition['config'] : [];
    }

    public static function stateFile(): ?string
    {
        $root = LegacyGlobals::get('ROOT', defined('ROOT') ? ROOT : '');

        if (!is_string($root) || trim($root) === '') {
            return null;
        }

        return rtrim($root, '/').'/custom/config/modules.php';
    }
}
