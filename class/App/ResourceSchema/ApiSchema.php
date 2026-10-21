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
            // `guard` vale solo se dichiarato con guard(); `page_token` è
            // l'opt-in al token di pagina (vedi allowPageToken()).
            'auth' => [
                'guard' => 'api_internal_user',
                'guard_declared' => false,
                'page_token' => false,
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

    /**
     * Authority API richiesta in più ai permessi `api` della Resource: il
     * token deve averla oltre a una di quelle di `permissionSchema()->api()`.
     * Senza questa chiamata non si applica.
     */
    public function guard(string $guard = 'api_internal_user'): self
    {
        $this->schema['auth']['guard'] = trim($guard);
        $this->schema['auth']['guard_declared'] = trim($guard) !== '';

        return $this;
    }

    /** Guard dichiarato con guard(), o null. */
    public function declaredGuard(): ?string
    {
        $auth = (array) ($this->schema['auth'] ?? []);
        $guard = trim((string) ($auth['guard'] ?? ''));

        return ($auth['guard_declared'] ?? false) === true && $guard !== '' ? $guard : null;
    }

    /**
     * Ammette il token di pagina (`API_TOKEN`, `typ = page`) sulle rotte API
     * della Resource. Default: rifiutato.
     */
    public function allowPageToken(bool $allow = true): self
    {
        $this->schema['auth']['page_token'] = $allow;

        return $this;
    }

    public function allowsPageToken(): bool
    {
        return ($this->schema['auth']['page_token'] ?? false) === true;
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
}
