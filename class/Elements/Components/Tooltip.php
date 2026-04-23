<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\CanSpanColumn;

class Tooltip extends Component
{
    use CanSpanColumn;

    public function __construct(
        protected string $text = '',
    ) {
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

    public function placement(string $placement = 'top'): self
    {
        return $this->schema('placement', trim($placement) !== '' ? trim($placement) : 'top');
    }

    public function icon(string $icon): self
    {
        return $this->schema('icon', trim($icon));
    }

    public function render(?string $theme = null): string
    {
        $text = trim($this->text);

        if ($text === '') {
            return '';
        }

        $placement = htmlspecialchars((string) ($this->schema['placement'] ?? 'top'), ENT_QUOTES, 'UTF-8');
        $icon = htmlspecialchars((string) ($this->schema['icon'] ?? 'bi bi-info-circle'), ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        return '<span class="text-body-secondary ms-1" data-bs-toggle="tooltip" data-bs-placement="'.$placement.'" data-bs-title="'.$title.'"><i class="'.$icon.'"></i></span>';
    }
}
