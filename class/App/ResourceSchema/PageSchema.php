<?php

namespace Wonder\App\ResourceSchema;

use RuntimeException;
use Wonder\App\Resource;

final class PageSchema
{
    private array $schema;

    private function __construct(
        private readonly string $resourceClass,
    ) {
        if (!is_subclass_of($this->resourceClass, Resource::class)) {
            throw new RuntimeException("{$this->resourceClass} deve estendere ".Resource::class);
        }

        $this->schema = [
            'layout' => 'backend.main',
            'pages' => [
                'list' => true,
                'create' => true,
                'store' => true,
                'view' => false,
                'edit' => true,
                'update' => true,
                'delete' => true,
            ],
            'views' => [
                'list' => null,
                'form' => null,
                'show' => null,
            ],
            'titles' => $this->resourceClass::defaultPageTitles(),
            'redirects' => [
                'store' => 'list',
                'update' => 'list',
                'delete' => 'list',
            ],
        ];
    }

    public static function for(string $resourceClass): self
    {
        return new self($resourceClass);
    }

    public function layout(string $layout): self
    {
        $this->schema['layout'] = trim($layout);

        return $this;
    }

    public function enable(string|array $pages, bool $enabled = true): self
    {
        foreach ($this->normalizeKeys($pages) as $page) {
            $this->schema['pages'][$page] = $enabled;
        }

        return $this;
    }

    public function disable(string|array $pages): self
    {
        return $this->enable($pages, false);
    }

    public function only(array $pages): self
    {
        $pages = $this->normalizeKeys($pages);

        foreach (array_keys($this->schema['pages']) as $page) {
            $this->schema['pages'][$page] = in_array($page, $pages, true);
        }

        return $this;
    }

    public function title(string $page, string $title): self
    {
        $this->schema['titles'][trim($page)] = trim($title);

        return $this;
    }

    public function titles(array $titles): self
    {
        foreach ($titles as $page => $title) {
            if (!is_string($page) || !is_string($title)) {
                continue;
            }

            $this->title($page, $title);
        }

        return $this;
    }

    public function view(string $slot, ?string $view): self
    {
        $this->schema['views'][trim($slot)] = $view !== null ? trim($view) : null;

        return $this;
    }

    public function redirect(string $action, string $target): self
    {
        $this->schema['redirects'][trim($action)] = trim($target);

        return $this;
    }

    public function toArray(): array
    {
        return $this->schema;
    }

    private function normalizeKeys(string|array $keys): array
    {
        $keys = is_array($keys) ? $keys : [$keys];
        $normalized = [];

        foreach ($keys as $key) {
            if (!is_string($key)) {
                continue;
            }

            $key = trim($key);

            if ($key !== '') {
                $normalized[] = $key;
            }
        }

        return array_values(array_unique($normalized));
    }
}
