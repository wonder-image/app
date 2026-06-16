<?php

namespace Wonder\Elements\Components;

use InvalidArgumentException;
use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\Renderer;

class ButtonGroup extends Component
{
    use CanSpanColumn, Renderer;

    private const ALLOWED_SIZES = ['', 'sm', 'lg'];

    /** @var array<int, object|string> */
    public array $components = [];

    public function __construct(array $components = [])
    {
        $this->components($components);
        $this->label('Button group');
    }

    public static function make(array $components = []): self
    {
        return new self($components);
    }

    public function components(array $components): self
    {
        $this->components = $components;

        return $this;
    }

    public function add(object|string $component): self
    {
        $this->components[] = $component;

        return $this;
    }

    public function label(string $label): self
    {
        return $this->schema('label', trim($label) !== '' ? $label : 'Button group');
    }

    public function toolbar(bool $toolbar = true): self
    {
        return $this->schema('toolbar', $toolbar);
    }

    public function vertical(bool $vertical = true): self
    {
        return $this->schema('vertical', $vertical);
    }

    public function size(string $size): self
    {
        $normalized = strtolower(trim($size));
        if (!in_array($normalized, self::ALLOWED_SIZES, true)) {
            throw new InvalidArgumentException(
                'Dimensione gruppo non valida. Valori ammessi: '.implode(', ', array_filter(self::ALLOWED_SIZES))
            );
        }

        return $this->schema('size', $normalized);
    }
}
