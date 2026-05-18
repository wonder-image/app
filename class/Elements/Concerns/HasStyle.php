<?php

namespace Wonder\Elements\Concerns;

trait HasStyle
{
    public function style(string $key, string|int|float $value): static
    {
        $styles = $this->normalizeStyleDeclarations($this->getAttr('style'));
        $styles[$this->normalizeStyleProperty($key)] = (string) $value;

        return $this->attr('style', $styles);
    }

    public function styles(array $styles): static
    {
        foreach ($styles as $property => $value) {
            if (!is_string($property) || $property === '' || $value === null || !is_scalar($value)) {
                continue;
            }

            $this->style($property, (string) $value);
        }

        return $this;
    }

    public function removeStyle(string $key): static
    {
        $styles = $this->normalizeStyleDeclarations($this->getAttr('style'));
        unset($styles[$this->normalizeStyleProperty($key)]);

        if ($styles === []) {
            return $this->removeAttr('style');
        }

        return $this->attr('style', $styles);
    }

    public function getStyle(?string $key = null): mixed
    {
        $styles = $this->normalizeStyleDeclarations($this->getAttr('style'));

        if ($key === null) {
            return $styles;
        }

        return $styles[$this->normalizeStyleProperty($key)] ?? null;
    }

    private function normalizeStyleDeclarations(mixed $styles): array
    {
        if (is_array($styles)) {
            $normalized = [];

            foreach ($styles as $property => $value) {
                if (!is_string($property) || trim($property) === '' || !is_scalar($value)) {
                    continue;
                }

                $normalized[$this->normalizeStyleProperty($property)] = trim((string) $value);
            }

            return $normalized;
        }

        if (!is_string($styles) || trim($styles) === '') {
            return [];
        }

        $normalized = [];
        foreach (explode(';', $styles) as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '' || !str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = explode(':', $declaration, 2);
            $property = $this->normalizeStyleProperty($property);
            $value = trim($value);

            if ($value === '') {
                continue;
            }

            $normalized[$property] = $value;
        }

        return $normalized;
    }

    private function normalizeStyleProperty(string $property): string
    {
        $property = strtolower(trim($property));

        if ($property === '') {
            throw new \InvalidArgumentException('La proprieta style non puo essere vuota.');
        }

        return $property;
    }
}
