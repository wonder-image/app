<?php

namespace Wonder\App\Permission;

use RuntimeException;

final class PermissionRegistry
{
    private const RESERVED_KEYS = ['links', 'function', 'verification'];

    /** @var array<string, Area> */
    private array $areas = [];

    /** @var array<string, array<string, Permission>> */
    private array $permissions = [];

    private function __construct(array $schema = [])
    {
        if ($schema !== []) {
            $this->merge($schema);
        }
    }

    public static function make(array $schema = []): self
    {
        return new self($schema);
    }

    public static function from(array|self $schema = []): self
    {
        if ($schema instanceof self) {
            return new self($schema->toArray());
        }

        return new self($schema);
    }

    public function addArea(
        Area|string $area,
        array $links = [],
        array $functions = [],
        array $verification = [],
        array $extra = [],
    ): self {
        if ($area instanceof Area) {
            $target = $this->upsertArea($area->key());
            $target->extra($area->toArray());

            return $this;
        }

        $this->area($area)->extra(array_replace_recursive($extra, array_filter([
            'links' => $links,
            'function' => $functions,
            'verification' => $verification,
        ], static fn (mixed $value): bool => $value !== [])));

        return $this;
    }

    public function area(string $area, ?array $definition = null): Area|self
    {
        $target = $this->upsertArea($area);

        if ($definition === null) {
            return $target;
        }

        $target->extra($definition);

        return $this;
    }

    public function getArea(string $area): ?Area
    {
        $area = trim($area);

        return $this->areas[$area] ?? null;
    }

    public function addPermission(
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
    ): self {
        if ($key instanceof Permission) {
            return $this->storePermission($key);
        }

        return $this->storePermission(
            Permission::make($key, $area)
                ->name($name)
                ->icon($icon)
                ->bg($bg)
                ->tx($tx)
                ->color($color)
                ->creator($creator)
                ->links($links)
                ->functions($functions)
                ->verifications($verification)
                ->extra($extra)
        );
    }

    public function permission(string $area, string $key, ?array $definition = null): Permission|self
    {
        $target = $this->upsertPermission($area, $key);

        if ($definition === null) {
            return $target;
        }

        $target->extra($definition);

        return $this;
    }

    public function getPermission(string $area, string $key): ?Permission
    {
        $area = trim($area);
        $key = trim($key);

        return $this->permissions[$area][$key] ?? null;
    }

    public function merge(array|self|callable|null $definitions): self
    {
        if ($definitions === null) {
            return $this;
        }

        if (is_callable($definitions) && !is_array($definitions)) {
            $result = $definitions($this);

            if ($result === null || $result === $this) {
                return $this;
            }

            return $this->merge($result);
        }

        $definitions = $definitions instanceof self ? $definitions->toArray() : $definitions;

        foreach ($definitions as $area => $entries) {
            if (!is_string($area) || !is_array($entries)) {
                continue;
            }

            [$areaDefinition, $permissionDefinitions] = self::splitAreaEntries($entries);

            if ($areaDefinition !== []) {
                $this->area($area)->extra($areaDefinition);
            } else {
                $this->upsertArea($area);
            }

            foreach ($permissionDefinitions as $permissionKey => $permissionDefinition) {
                $this->permission($area, $permissionKey)->extra($permissionDefinition);
            }
        }

        return $this;
    }

    public function mergeFile(string $file): self
    {
        if (!is_file($file)) {
            return $this;
        }

        return $this->merge(self::filePayload($file));
    }

    public function toArray(): array
    {
        $schema = [];

        foreach ($this->areas as $areaKey => $area) {
            $schema[$areaKey] = $area->toArray();

            foreach ($this->permissions[$areaKey] ?? [] as $permissionKey => $permission) {
                $schema[$areaKey][$permissionKey] = $permission->toArray();
            }
        }

        return $schema;
    }

    public static function fromFile(string $file): array|self|callable|null
    {
        if (!is_file($file)) {
            return null;
        }

        return self::filePayload($file);
    }

    private function upsertArea(string $area): Area
    {
        $area = trim($area);

        if ($area === '') {
            throw new RuntimeException('Area permesso non valida.');
        }

        if (!isset($this->areas[$area])) {
            $this->areas[$area] = Area::make($area);
        }

        return $this->areas[$area];
    }

    private function upsertPermission(string $area, string $key): Permission
    {
        $area = trim($area);
        $key = trim($key);

        if ($area === '' || $key === '') {
            throw new RuntimeException('Area o chiave permesso non valida.');
        }

        $areaDefinition = $this->upsertArea($area);

        if (!isset($this->permissions[$area][$key])) {
            $this->permissions[$area][$key] = Permission::make($key, $areaDefinition);
        } else {
            $this->permissions[$area][$key]->area($areaDefinition);
        }

        return $this->permissions[$area][$key];
    }

    private function storePermission(Permission $permission): self
    {
        $areaKey = $permission->areaKey();

        if ($areaKey === '') {
            throw new RuntimeException('Area o chiave permesso non valida.');
        }

        $areaDefinition = $permission->areaDefinition();

        if ($areaDefinition instanceof Area) {
            $this->addArea($areaDefinition);
        }

        $targetArea = $this->upsertArea($areaKey);
        $target = $this->upsertPermission($areaKey, $permission->key());

        $target->area($targetArea)->extra($permission->toArray());

        return $this;
    }

    private static function splitAreaEntries(array $entries): array
    {
        $areaDefinition = [];
        $permissionDefinitions = [];

        foreach ($entries as $key => $value) {
            if (self::isPermissionDefinition($key, $value)) {
                $permissionDefinitions[$key] = $value;
                continue;
            }

            $areaDefinition[$key] = $value;
        }

        return [$areaDefinition, $permissionDefinitions];
    }

    private static function isPermissionDefinition(mixed $key, mixed $value): bool
    {
        if (!is_string($key) || in_array($key, self::RESERVED_KEYS, true)) {
            return false;
        }

        return is_array($value) && array_key_exists('name', $value);
    }

    private static function filePayload(string $file): array|self|callable|null
    {
        $legacyPermits = [];

        $loaded = (static function (string $__file) use (&$legacyPermits) {
            // Compat legacy: un file permissions senza return puo' ancora valorizzare $CUSTOM_PERMITS.
            $CUSTOM_PERMITS = [];
            $result = require $__file;
            $legacyPermits = is_array($CUSTOM_PERMITS) ? $CUSTOM_PERMITS : [];

            return $result;
        })($file);

        if ($loaded instanceof self || is_array($loaded) || is_callable($loaded)) {
            return $loaded;
        }

        if (($loaded === null || $loaded === true || $loaded === 1) && $legacyPermits !== []) {
            return $legacyPermits;
        }

        if ($loaded === null || $loaded === true || $loaded === 1) {
            return null;
        }

        throw new RuntimeException('Il file permissions '.$file.' deve restituire un array, una '.self::class.' o una callable.');
    }
}
