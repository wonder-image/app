<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\HasText;
use Wonder\Elements\Concerns\Renderer;

class HelpText extends Component
{
    use CanSpanColumn, HasText, Renderer;

    public function __construct(string $text = '')
    {
        $this->text = $text;
        $this->muted();
    }

    public static function make(string $text): self
    {
        return new self($text);
    }
}
