<?php

namespace Wonder\App\Permission;

use RuntimeException;

final class Permission
{
    private array $definition = [];

    private function __construct(
        private readonly string $key,
        private Area|string|null $area = null,
    ) {
        if ($this->key === '') {
            throw new RuntimeException('Chiave permesso non valida.');
        }
    }

    public static function make(string $key, Area|string|null $area = null): self
    {
        $area = is_string($area) ? trim($area) : $area;

        return new self(trim($key), $area);
    }

    public static function fromArray(string $key, Area|string|null $area = null, array $definition = []): self
    {
        return self::make($key, $area)->extra($definition);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function area(Area|string $area): self
    {
        $this->area = is_string($area) ? trim($area) : $area;

        return $this;
    }

    public function areaKey(): string
    {
        if ($this->area instanceof Area) {
            return $this->area->key();
        }

        return is_string($this->area) ? trim($this->area) : '';
    }

    public function areaDefinition(): ?Area
    {
        return $this->area instanceof Area ? $this->area : null;
    }

    public function name(string $name): self
    {
        $this->definition['name'] = trim($name);

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->definition['icon'] = $icon;

        return $this;
    }

    public function bg(string $bg): self
    {
        $this->definition['bg'] = trim($bg);

        return $this;
    }

    public function tx(string $tx): self
    {
        $this->definition['tx'] = trim($tx);

        return $this;
    }

    public function color(string $color): self
    {
        $this->definition['color'] = trim($color);

        return $this;
    }

    public function creator(array $creator): self
    {
        $this->definition['creator'] = array_values($creator);

        return $this;
    }

    public function route(string $key, string $routeName): self
    {
        return $this->link($key, function_exists('__r') ? __r($routeName) : $routeName);
    }

    public function link(string $key, string $value): self
    {
        $key = trim($key);

        if ($key !== '') {
            $this->definition['links'][$key] = $value;
        }

        return $this;
    }

    public function links(array $links): self
    {
        foreach ($links as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $this->link($key, $value);
            }
        }

        return $this;
    }

    public function removeLink(string $key): self
    {
        unset($this->definition['links'][trim($key)]);

        return $this;
    }

    public function function(string $key, string $callback): self
    {
        $key = trim($key);

        if ($key !== '') {
            $this->definition['function'][$key] = $callback;
        }

        return $this;
    }

    public function functions(array $functions): self
    {
        foreach ($functions as $key => $callback) {
            if (is_string($key) && is_string($callback)) {
                $this->function($key, $callback);
            }
        }

        return $this;
    }

    public function removeFunction(string $key): self
    {
        unset($this->definition['function'][trim($key)]);

        return $this;
    }

    public function verification(string $key, array $config): self
    {
        $key = trim($key);

        if ($key !== '') {
            $this->definition['verification'][$key] = $config;
        }

        return $this;
    }

    public function verifications(array $verifications): self
    {
        foreach ($verifications as $key => $config) {
            if (is_string($key) && is_array($config)) {
                $this->verification($key, $config);
            }
        }

        return $this;
    }

    public function removeVerification(string $key): self
    {
        unset($this->definition['verification'][trim($key)]);

        return $this;
    }

    public function extra(array $extra): self
    {
        $this->definition = array_replace_recursive($this->definition, $extra);

        return $this;
    }

    public function toArray(): array
    {
        return $this->definition;
    }
}
