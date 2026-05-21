<?php

namespace Wonder\App\Permission;

final class Permissions
{
    private static ?PermissionRegistry $registry = null;

    public static function reset(array|PermissionRegistry $schema = []): PermissionRegistry
    {
        self::$registry = PermissionRegistry::from($schema);

        return self::$registry;
    }

    public static function replace(array|PermissionRegistry $schema = []): PermissionRegistry
    {
        return self::reset($schema);
    }

    public static function instance(): PermissionRegistry
    {
        if (!self::$registry instanceof PermissionRegistry) {
            self::$registry = PermissionRegistry::make();
        }

        return self::$registry;
    }

    public static function addArea(
        Area|string $area,
        array $links = [],
        array $functions = [],
        array $verification = [],
        array $extra = [],
    ): PermissionRegistry {
        return self::instance()->addArea($area, $links, $functions, $verification, $extra);
    }

    public static function area(string $area, ?array $definition = null): Area|PermissionRegistry
    {
        return self::instance()->area($area, $definition);
    }

    public static function getArea(string $area): ?Area
    {
        return self::instance()->getArea($area);
    }

    public static function addPermission(
        Permission|string $key,
        Area|string|null $area = null,
        string $name = '',
        string $icon = '',
        string $bg = '',
        string $tx = '',
        string $color = '',
        array $creator = [],
        array $links = [],
        array $functions = [],
        array $verification = [],
        array $extra = [],
    ): PermissionRegistry {
        return self::instance()->addPermission(
            $key,
            $area,
            $name,
            $icon,
            $bg,
            $tx,
            $color,
            $creator,
            $links,
            $functions,
            $verification,
            $extra
        );
    }

    public static function permission(string $area, string $key, ?array $definition = null): Permission|PermissionRegistry
    {
        return self::instance()->permission($area, $key, $definition);
    }

    public static function getPermission(string $area, string $key): ?Permission
    {
        return self::instance()->getPermission($area, $key);
    }

    public static function merge(array|PermissionRegistry|callable|null $definitions): PermissionRegistry
    {
        return self::instance()->merge($definitions);
    }

    public static function mergeFile(string $file): PermissionRegistry
    {
        return self::instance()->mergeFile($file);
    }

    public static function toArray(): array
    {
        return self::instance()->toArray();
    }
}
