<?php // class/View/ComponentNamespaceRegistry.php

namespace Wonder\View;

final class ComponentNamespaceRegistry
{
    /** @var array<string,string> prefisso => dir base assoluta dei componenti */
    private static array $map = [];

    public static function register(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, "/ \t\n\r\0\x0B");
        if ($prefix === '') {
            return;
        }
        self::$map[$prefix] = rtrim($baseDir, '/');
    }

    public static function has(string $prefix): bool
    {
        return isset(self::$map[$prefix]);
    }

    public static function base(string $prefix): ?string
    {
        return self::$map[$prefix] ?? null;
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        return self::$map;
    }

    public static function reset(): void
    {
        self::$map = [];
    }
}
