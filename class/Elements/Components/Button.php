<?php

namespace Wonder\Elements\Components;

use InvalidArgumentException;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\Renderer;

class Button extends Link
{
    use CanSpanColumn, Renderer;

    private const ALLOWED_SIZES = ['', 'sm', 'lg'];
    private const ALLOWED_TYPES = ['a', 'button', 'submit', 'reset'];

    public function __construct(string $label = '', string $href = '')
    {
        parent::__construct($href, $label);

        $this->variant('primary');
        $this->type('button');
    }

    public static function make(string $label = '', string $href = ''): self
    {
        return new self($label, $href);
    }

    public static function to(string $href, string $label): self
    {
        return new self($label, $href);
    }

    public function variant(string $variant): self
    {
        $variant = strtolower(trim($variant));

        return $this->schema('variant', $variant !== '' ? $variant : 'primary');
    }

    public function outline(bool $outline = true): self
    {
        return $this->schema('outline', $outline);
    }

    public function size(string $size): self
    {
        $normalized = strtolower(trim($size));
        if (!in_array($normalized, self::ALLOWED_SIZES, true)) {
            throw new InvalidArgumentException(
                'Dimensione bottone non valida. Valori ammessi: '.implode(', ', array_filter(self::ALLOWED_SIZES))
            );
        }

        return $this->schema('size', $normalized);
    }

    public function type(string $type): self
    {
        $normalized = strtolower(trim($type));
        if (!in_array($normalized, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException(
                'Tipo bottone non valido. Valori ammessi: '.implode(', ', self::ALLOWED_TYPES)
            );
        }

        return $this->schema('type', $normalized);
    }

    public function disabled(bool $disabled = true): self
    {
        return $this->schema('disabled', $disabled);
    }

    public function active(bool $active = true): self
    {
        return $this->schema('active', $active);
    }

    public function block(bool $block = true): self
    {
        return $this->schema('block', $block);
    }

    public function nowrap(bool $nowrap = true): self
    {
        return $this->schema('nowrap', $nowrap);
    }

    public function arrow(bool $arrow = true): self
    {
        if ($arrow && trim((string) ($this->schema['icon'] ?? '')) === '') {
            $this->icon('bi bi-chevron-right', 'end');
        }

        return $this->schema('arrow', $arrow);
    }
}
