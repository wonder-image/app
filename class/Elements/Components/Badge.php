<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\Renderer;

class Badge extends Link
{
    use CanSpanColumn, Renderer;

    public function __construct(string $label = '', string $href = '')
    {
        parent::__construct($href, $label);

        $this->variant('secondary');
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

        return $this->schema('variant', $variant !== '' ? $variant : 'secondary');
    }

    public function outline(bool $outline = true): self
    {
        return $this->schema('outline', $outline);
    }

    public function pill(bool $pill = true): self
    {
        return $this->schema('pill', $pill);
    }
}
