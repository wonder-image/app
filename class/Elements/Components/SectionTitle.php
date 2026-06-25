<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\HasText;
use Wonder\Elements\Concerns\Renderer;

class SectionTitle extends Component
{
    use CanSpanColumn, HasText, Renderer;

    public function __construct(string $text = '')
    {
        $this->text = $text;
        $this->level(6);
    }

    public static function make(string $text): self
    {
        return new self($text);
    }

    public function level(int $level = 6): self
    {
        $level = max(1, min(6, $level));

        return $this->schema('level', 'h' . $level);
    }

    public function tooltip(string $text): self
    {
        return $this->schema('tooltip', $text);
    }
}
