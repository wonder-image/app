<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;

class SectionTitle extends Component
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

    public function level(string $level = 'h6'): self
    {
        return $this->schema('level', trim($level) !== '' ? trim($level) : 'h6');
    }

    public function tooltip(string $text): self
    {
        return $this->schema('tooltip', $text);
    }

    public function render(?string $theme = null): string
    {
        $tag = strtolower((string) ($this->schema['level'] ?? 'h6'));

        if (!in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            $tag = 'h6';
        }

        $label = htmlspecialchars(trim($this->text), ENT_QUOTES, 'UTF-8');
        $tooltipText = trim((string) ($this->schema['tooltip'] ?? ''));
        $tooltip = $tooltipText !== ''
            ? Tooltip::make($tooltipText)->render()
            : '';

        return '<'.$tag.'>'.$label.$tooltip.'</'.$tag.'>';
    }
}
