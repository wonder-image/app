<?php

namespace Wonder\App\ResourceSchema;

use RuntimeException;
use Wonder\App\Resource;

final class ApiSchema
{
    private array $schema;

    private function __construct(
        private readonly string $resourceClass,
    ) {
        if (!is_subclass_of($this->resourceClass, Resource::class)) {
            throw new RuntimeException("{$this->resourceClass} deve estendere ".Resource::class);
        }

        $this->schema = [
            'enabled' => true,
            'routes' => [
                'index' => true,
                'store' => true,
                'show' => true,
                'update' => true,
                'destroy' => true,
            ],
            'auth' => [
                'guard' => 'api_internal_user',
            ],
            'fields' => [
                'index' => [],
                'show' => [],
                'store' => [],
                'update' => [],
            ],
            'pagination' => [
                'enabled' => true,
                'default_limit' => 25,
                'max_limit' => 100,
            ],
        ];
    }

    public static function for(string $resourceClass): self
    {
        return new self($resourceClass);
    }

    public function enabled(bool $enabled = true): self
    {
        $this->schema['enabled'] = $enabled;

        return $this;
    }

    public function route(string $route, bool $enabled = true): self
    {
        $this->schema['routes'][trim($route)] = $enabled;

        return $this;
    }

    public function only(array $routes): self
    {
        $routes = array_values(array_unique(array_filter($routes, 'is_string')));

        foreach (array_keys($this->schema['routes']) as $route) {
            $this->schema['routes'][$route] = in_array($route, $routes, true);
        }

        return $this;
    }

    public function fields(string $action, array $fields): self
    {
        $this->schema['fields'][trim($action)] = array_values(array_unique(array_filter($fields, 'is_string')));

        return $this;
    }

    public function guard(string $guard): self
    {
        $this->schema['auth']['guard'] = trim($guard);

        return $this;
    }

    public function pagination(bool $enabled = true, int $defaultLimit = 25, int $maxLimit = 100): self
    {
        $this->schema['pagination'] = [
            'enabled' => $enabled,
            'default_limit' => $defaultLimit,
            'max_limit' => $maxLimit,
        ];

        return $this;
    }

    public function toArray(): array
    {
        return $this->schema;
    }
}
