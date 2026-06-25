<?php

namespace Wonder\App\Permission;

use RuntimeException;

final class Area
{
    private array $definition = [];

    private function __construct(
        private readonly string $key,
    ) {
        if ($this->key === '') {
            throw new RuntimeException('Area permesso non valida.');
        }
    }

    public static function make(string $key): self
    {
        return new self(trim($key));
    }

    public static function fromArray(string $key, array $definition = []): self
    {
        return self::make($key)->extra($definition);
    }

    public function key(): string
    {
        return $this->key;
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
