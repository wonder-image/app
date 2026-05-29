<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;
use Wonder\Elements\Concerns\HasText;
use Wonder\Elements\Concerns\Renderer;

class Text extends Component
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

    public function tag(string $tag): self
    {
        return $this->schema('tag', strtolower(trim($tag)));
    }

    public function lead(bool $lead = true): self
    {
        return $this->schema('lead', $lead);
    }
}
