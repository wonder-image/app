<?php

namespace Wonder\App\ResourceSchema;

use RuntimeException;
use Wonder\App\Resource;

final class NavigationSchema
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
            'section' => '',
            'section_folder' => '',
            'section_icon' => '',
            'title' => $this->resourceClass::titleLabel(),
            'order' => 100,
            'file' => 'list',
            'authority' => [],
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

    public function section(string $title, string $folder, string $icon, array $authority = []): self
    {
        $this->schema['section'] = trim($title);
        $this->schema['section_folder'] = trim($folder);
        $this->schema['section_icon'] = trim($icon);

        if ($authority !== []) {
            $this->schema['authority'] = $authority;
        }

        return $this;
    }

    public function title(string $title): self
    {
        $this->schema['title'] = trim($title);

        return $this;
    }

    public function order(int $order): self
    {
        $this->schema['order'] = $order;

        return $this;
    }

    public function file(string $file): self
    {
        $this->schema['file'] = trim($file);

        return $this;
    }

    public function authority(array $authority): self
    {
        $this->schema['authority'] = $authority;

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
