<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\HasText;
use Wonder\Elements\Concerns\Renderer;

class Tooltip extends Component
{
    use CanSpanColumn, HasText, Renderer;

    public function __construct(string $text = '')
    {
        $this->text = $text;
    }

    public static function make(string $text): self
    {
        return new self($text);
    }

    public function placement(string $placement = 'top'): self
    {
        return $this->schema('placement', trim($placement) !== '' ? trim($placement) : 'top');
    }

    public function icon(string $icon): self
    {
        return $this->schema('icon', trim($icon));
    }
}
