<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\HasText;
use Wonder\Elements\Concerns\Renderer;

class Accordion extends Component
{
    use CanSpanColumn, HasText, Renderer;

    public array $components = [];

    public function __construct(string $text = '')
    {
        $this->text = $text;
        $this->columnSpan(12);
    }

    public static function make(string $text): self
    {
        return new self($text);
    }

    public function components(array $components): self
    {
        $this->components = $components;

        return $this;
    }

    public function expanded(bool $expanded = true): self
    {
        return $this->schema('expanded', $expanded);
    }

    public function flush(bool $flush = true): self
    {
        return $this->schema('flush', $flush);
    }
}
