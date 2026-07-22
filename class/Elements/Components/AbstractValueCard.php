<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\Renderer;

abstract class AbstractValueCard extends Component
{
    use CanSpanColumn, Renderer;

    public function __construct(
        string $title = '',
        string|int|float|bool|null $value = null
    ) {
        $this->title($title);
        $this->value($value);
        $this->placeholder('--');
    }

    public static function make(
        string $title = '',
        string|int|float|bool|null $value = null
    ): static {
        return new static($title, $value);
    }

    public function title(string $title): static
    {
        return $this->schema('title', $title);
    }

    public function value(string|int|float|bool|null $value): static
    {
        return $this->schema('value', $value);
    }

    public function placeholder(string $placeholder = '--'): static
    {
        return $this->schema('placeholder', $placeholder);
    }

    public function valueLevel(int $level): static
    {
        return $this->schema('value_level', max(1, min(6, $level)));
    }
}
