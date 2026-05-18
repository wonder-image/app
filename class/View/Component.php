<?php

namespace Wonder\View;

use Stringable;
use Throwable;
use Wonder\Elements\Component as BaseComponent;

class Component extends BaseComponent
{
    public function __construct(
        private readonly string $component,
        private array $data = [],
    ) {}

    public static function make(string $component, array $data = []): static
    {
        return new static($component, $data);
    }

    public function data(string|array $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);

            return $this;
        }

        $this->data[$key] = $value;

        return $this;
    }

    public function render(): string
    {
        return View::component($this->component, $this->payload());
    }

    public function __toString(): string
    {
        try {
            return $this->render();
        } catch (Throwable) {
            return '';
        }
    }

    public function payload(): array
    {
        $payload = $this->data;
        $attributes = $this->normalizeAttributes((array) ($this->schema['attributes'] ?? []));
        $classes = [];

        if (!empty($payload['class'])) {
            $classes[] = trim((string) $payload['class']);
        }

        if (isset($attributes['class'])) {
            $classes = array_merge($classes, $attributes['class']);
            unset($attributes['class']);
        }

        if (isset($this->schema['id']) && $this->schema['id'] !== '') {
            $payload['id'] = (string) $this->schema['id'];
        }

        $class = trim(implode(' ', array_filter($classes, static fn ($value): bool => $value !== '')));
        if ($class !== '') {
            $payload['class'] = $class;
        }

        $rawAttributes = $this->renderAttributes($attributes);
        $payloadAttributes = $payload['attributes'] ?? '';

        if (is_array($payloadAttributes)) {
            $payloadAttributes = $this->renderAttributes($this->normalizeAttributes($payloadAttributes));
        }

        $attributesString = trim(implode(' ', array_filter([
            trim((string) $payloadAttributes),
            $rawAttributes,
        ])));

        if ($attributesString !== '') {
            $payload['attributes'] = $attributesString;
        }

        return $payload;
    }

    private function normalizeAttributes(array $attributes): array
    {
        $normalized = [];

        foreach ($attributes as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if ($key === 'class') {
                $normalized[$key] = $this->normalizeClassValues($value);
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function normalizeClassValues(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn ($item): string => trim((string) $item),
                $value
            )));
        }

        $value = trim((string) $value);

        return $value === '' ? [] : [$value];
    }

    private function renderAttributes(array $attributes): string
    {
        $html = [];

        foreach ($attributes as $key => $value) {
            $key = trim($key);
            if ($key === '' || $value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                $html[] = $key;
                continue;
            }

            if (is_array($value)) {
                $value = implode(' ', array_filter(array_map(
                    static fn ($item): string => trim((string) $item),
                    $value
                )));
            } elseif (!is_scalar($value) && !$value instanceof Stringable) {
                continue;
            }

            $html[] = $key.'="'.self::escape((string) $value).'"';
        }

        return implode(' ', $html);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
