<?php

namespace Wonder\App\ResourceSchema;

use RuntimeException;
use Wonder\App\Resource;

final class TableLayoutSchema
{
    private array $schema;

    private function __construct(
        private readonly string $resourceClass,
    ) {
        if (!is_subclass_of($this->resourceClass, Resource::class)) {
            throw new RuntimeException("{$this->resourceClass} deve estendere ".Resource::class);
        }

        $this->schema = [
            'title' => [
                'enabled' => true,
                'text' => null,
            ],
            'results' => true,
            'button_add' => [
                'enabled' => true,
                'label' => null,
            ],
            'filters' => [
                'search' => [
                    'enabled' => true,
                ],
                'limit' => [
                    'enabled' => true,
                ],
            ],
        ];
    }

    public static function for(string $resourceClass): self
    {
        return new self($resourceClass);
    }

    public function title(bool|string $enabled = true, ?string $text = null): self
    {
        if (is_string($enabled)) {
            $text = $enabled;
            $enabled = true;
        }

        $this->schema['title']['enabled'] = (bool) $enabled;

        if ($text !== null) {
            $this->schema['title']['text'] = trim($text);
        }

        return $this;
    }

    public function hideTitle(): self
    {
        return $this->title(false);
    }

    public function results(bool $enabled = true): self
    {
        $this->schema['results'] = $enabled;

        return $this;
    }

    public function buttonAdd(bool|string $enabled = true, ?string $label = null): self
    {
        if (is_string($enabled)) {
            $label = $enabled;
            $enabled = true;
        }

        $this->schema['button_add']['enabled'] = (bool) $enabled;

        if ($label !== null) {
            $this->schema['button_add']['label'] = trim($label);
        }

        return $this;
    }

    public function hideButtonAdd(): self
    {
        return $this->buttonAdd(false);
    }

    public function filterSearch(bool $enabled = true): self
    {
        $this->schema['filters']['search']['enabled'] = $enabled;

        return $this;
    }

    public function filterLimit(bool $enabled = true): self
    {
        $this->schema['filters']['limit']['enabled'] = $enabled;

        return $this;
    }

    public function filters(bool $search = true, bool $limit = true): self
    {
        return $this
            ->filterSearch($search)
            ->filterLimit($limit);
    }

    public function cleanHeader(): self
    {
        return $this
            ->hideTitle()
            ->results(false)
            ->hideButtonAdd();
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

    public function toArray(): array
    {
        return $this->all();
    }
}
