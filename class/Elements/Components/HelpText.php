<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;

class HelpText extends Component
{
    use CanSpanColumn;

    public function __construct(
        protected string $text = '',
    ) {
        $this->columnSpan(12);
    }

    public static function make(string $text): self
    {
        return new self($text);
    }

    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function muted(bool $muted = true): self
    {
        return $this->schema('muted', $muted);
    }

    public function small(bool $small = true): self
    {
        return $this->schema('small', $small);
    }

    public function render(?string $theme = null): string
    {
        $classes = [];

        if (($this->schema['muted'] ?? true) === true) {
            $classes[] = 'text-body-secondary';
        }

        if (($this->schema['small'] ?? false) === true) {
            $classes[] = 'small';
        }

        $class = $classes !== [] ? ' class="'.htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8').'"' : '';

        return '<div'.$class.'>'.$this->text.'</div>';
    }
}
