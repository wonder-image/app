<?php

namespace Wonder\App;

class LegacyGlobals
{
    public const RUNTIME_CONTEXT_KEY = 'RUNTIME_CONTEXT';

    private const STORAGE_KEY = '__LEGACY_GLOBALS';

    private const DEFINITIONS = [
        'ROOT' => [ 'group' => 'config', 'kind' => 'static' ],
        'APP_VERSION' => [ 'group' => 'config', 'kind' => 'static' ],
        'ROOT_APP' => [ 'group' => 'config', 'kind' => 'static' ],
        'ROOT_RESOURCES' => [ 'group' => 'config', 'kind' => 'static' ],
        'SEO' => [ 'group' => 'config', 'kind' => 'runtime_projection' ],
        'DB' => [ 'group' => 'config', 'kind' => 'service_config' ],
        'MAIL' => [ 'group' => 'config', 'kind' => 'service_config' ],
        'COLOR' => [ 'group' => 'config', 'kind' => 'style' ],
        'FONT' => [ 'group' => 'config', 'kind' => 'style' ],
        'PATH' => [ 'group' => 'config', 'kind' => 'paths' ],
        'SOCIETY' => [ 'group' => 'runtime', 'kind' => 'request_data' ],
        'TABLE' => [ 'group' => 'config', 'kind' => 'schema_registry' ],
        'ANALYTICS' => [ 'group' => 'config', 'kind' => 'service_config' ],
        'API' => [ 'group' => 'config', 'kind' => 'service_config' ],
        'DEFAULT' => [ 'group' => 'config', 'kind' => 'defaults' ],
        'PAGE' => [ 'group' => 'runtime', 'kind' => 'request_data' ],
        'PERMITS' => [ 'group' => 'config', 'kind' => 'permissions' ],
        'MYSQLI_CONNECTION' => [ 'group' => 'service', 'kind' => 'pool' ],
        'mysqli' => [ 'group' => 'service', 'kind' => 'legacy_alias' ],
        'BACKEND' => [ 'group' => 'runtime', 'kind' => 'route_flag' ],
        'FRONTEND' => [ 'group' => 'runtime', 'kind' => 'route_flag' ],
        'PRIVATE' => [ 'group' => 'runtime', 'kind' => 'route_flag' ],
        'PERMIT' => [ 'group' => 'runtime', 'kind' => 'route_flag' ],
        'ROUTE_PARAMETERS' => [ 'group' => 'runtime', 'kind' => 'route_data' ],
        'ROUTE_META' => [ 'group' => 'runtime', 'kind' => 'route_data' ],
        'ALERT' => [ 'group' => 'runtime', 'kind' => 'flash_state' ],
        'ERROR' => [ 'group' => 'runtime', 'kind' => 'error_state' ],
        'USER' => [ 'group' => 'runtime', 'kind' => 'request_data' ],
    ];

    public static function definitions(): array
    {
        return self::DEFINITIONS;
    }

    public static function names(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    public static function groups(): array
    {
        $groups = [];

        foreach (self::DEFINITIONS as $definition) {
            $group = (string) ($definition['group'] ?? 'runtime');

            if (!in_array($group, $groups, true)) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    public static function capture(array $scope): array
    {
        $captured = [];

        foreach (self::names() as $key) {
            if (!array_key_exists($key, $scope)) {
                continue;
            }

            self::set($key, $scope[$key]);
            $captured[$key] = $scope[$key];
        }

        return $captured;
    }

    public static function share(array $context): array
    {
        $shared = [];

        foreach ($context as $key => $value) {
            if (!self::isValidKey($key)) {
                continue;
            }

            self::set($key, $value);
            $shared[$key] = $value;
        }

        return $shared;
    }

    public static function set(string $key, mixed $value): void
    {
        if (!self::isValidKey($key)) {
            return;
        }

        $GLOBALS[$key] = $value;

        if (self::isKnown($key)) {
            $stored = self::stored();
            $stored[$key] = $value;
            self::store($stored);
            return;
        }

        $runtimeContext = self::runtimeContext();
        $runtimeContext[$key] = $value;
        self::storeRuntimeContext($runtimeContext);
    }

    public static function has(string $key): bool
    {
        if (array_key_exists($key, $GLOBALS)) {
            return true;
        }

        if (array_key_exists($key, self::stored())) {
            return true;
        }

        return array_key_exists($key, self::runtimeContext());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $GLOBALS)) {
            return $GLOBALS[$key];
        }

        $stored = self::stored();

        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        $runtimeContext = self::runtimeContext();

        if (array_key_exists($key, $runtimeContext)) {
            return $runtimeContext[$key];
        }

        return $default;
    }

    public static function scope(?array $keys = null): array
    {
        $scope = [];
        $selectedKeys = is_array($keys) ? $keys : self::names();

        foreach ($selectedKeys as $key) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (!self::has($key)) {
                continue;
            }

            $scope[$key] = self::get($key);
        }

        if ($keys !== null) {
            return $scope;
        }

        foreach (self::runtimeContext() as $key => $value) {
            if (!array_key_exists($key, $scope)) {
                $scope[$key] = $value;
            }
        }

        return $scope;
    }

    public static function section(string $group): array
    {
        $section = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            if (($definition['group'] ?? null) !== $group) {
                continue;
            }

            if (!self::has($key)) {
                continue;
            }

            $section[$key] = self::get($key);
        }

        return $section;
    }

    public static function runtimeContext(): array
    {
        $runtimeContext = $GLOBALS[self::RUNTIME_CONTEXT_KEY] ?? [];

        return is_array($runtimeContext) ? $runtimeContext : [];
    }

    private static function stored(): array
    {
        $stored = $GLOBALS[self::STORAGE_KEY] ?? [];

        return is_array($stored) ? $stored : [];
    }

    private static function store(array $values): void
    {
        $GLOBALS[self::STORAGE_KEY] = $values;
    }

    private static function storeRuntimeContext(array $runtimeContext): void
    {
        $GLOBALS[self::RUNTIME_CONTEXT_KEY] = $runtimeContext;
    }

    private static function isKnown(string $key): bool
    {
        return array_key_exists($key, self::DEFINITIONS);
    }

    private static function isValidKey(mixed $key): bool
    {
        if (!is_string($key)) {
            return false;
        }

        $key = trim($key);

        if ($key === '') {
            return false;
        }

        if (in_array($key, [ self::RUNTIME_CONTEXT_KEY, self::STORAGE_KEY ], true)) {
            return false;
        }

        return str_starts_with($key, '__') === false;
    }
}
