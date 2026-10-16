<?php

namespace Wonder\App\ResourceSchema;

use RuntimeException;
use Wonder\App\Permission\Access;
use Wonder\App\Resource;

final class PermissionSchema
{
    private array $schema;

    private function __construct(
        private readonly string $resourceClass,
    ) {
        if (!is_subclass_of($this->resourceClass, Resource::class)) {
            throw new RuntimeException("{$this->resourceClass} deve estendere ".Resource::class);
        }

        $this->schema = [
            'backend' => [
                'list' => [],
                'create' => [],
                'store' => [],
                'view' => [],
                'edit' => [],
                'update' => [],
                'delete' => [],
            ],
            'api' => [
                'index' => [],
                'store' => [],
                'show' => [],
                'update' => [],
                'destroy' => [],
            ],
        ];
    }

    public static function for(string $resourceClass): self
    {
        return new self($resourceClass);
    }

    public function allow(string $area, string|array $actions, array $authorities = []): self
    {
        $area = trim($area);

        if (!isset($this->schema[$area]) || !is_array($this->schema[$area])) {
            throw new RuntimeException("Area permessi non supportata: {$area}");
        }

        foreach ($this->normalizeActions($actions) as $action) {
            $this->schema[$area][$action] = $authorities;
        }

        return $this;
    }

    public function backend(string|array $actions, array $authorities = []): self
    {
        return $this->allow('backend', $actions, $authorities);
    }

    /**
     * Permessi backend presi da un'area di `Access` (es. `log`,
     * `gestionale:catalogo`): view → list/view, create → create/store,
     * edit → edit/update, delete → delete. Le azioni API restano esplicite.
     *
     * `$actions` limita le azioni backend abilitate (es. `['list', 'view']` per
     * una Resource in sola lettura); vuoto: tutte quelle dell'area.
     */
    public function access(string $key, string|array $actions = []): self
    {
        $only = array_values(array_filter(array_map('trim', (array) $actions)));

        foreach (Access::backendActions($key) as $action => $authorities) {
            if ($only === [] || in_array($action, $only, true)) {
                $this->schema['backend'][$action] = $authorities;
            }
        }

        return $this;
    }

    public function api(string|array $actions, array $authorities = []): self
    {
        return $this->allow('api', $actions, $authorities);
    }

    public function backendCrud(array $authorities = []): self
    {
        return $this->backend(['list', 'create', 'store', 'view', 'edit', 'update', 'delete'], $authorities);
    }

    public function apiCrud(array $authorities = []): self
    {
        return $this->api(['index', 'store', 'show', 'update', 'destroy'], $authorities);
    }

    public function toArray(): array
    {
        return $this->all();
    }

    public function get(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->schema;
        }

        return $this->schema[$key] ?? null;
    }

    public function all(): array
    {
        return $this->schema;
    }

    private function normalizeActions(string|array $actions): array
    {
        $actions = is_array($actions) ? $actions : [$actions];
        $normalized = [];

        foreach ($actions as $action) {
            if (!is_string($action)) {
                continue;
            }

            $action = trim($action);

            if ($action !== '') {
                $normalized[] = $action;
            }
        }

        return array_values(array_unique($normalized));
    }
}
