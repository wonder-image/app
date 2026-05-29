<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\RendersText;

class Tooltip extends Component
{
    use RendersText;

    public function render($class): string
    {
        $text = trim($class->getText());

        if ($text === '') {
            return '';
        }

        $schema = $class->getSchema();
        $placement = $this->escape((string) ($schema['placement'] ?? 'top'));
        $icon = $this->escape((string) ($schema['icon'] ?? 'bi bi-info-circle'));
        $title = $this->escape($text);

        return '<span class="text-body-secondary ms-1" data-bs-toggle="tooltip"'
            . ' data-bs-placement="' . $placement . '"'
            . ' data-bs-title="' . $title . '">'
            . '<i class="' . $icon . '"></i></span>';
    }
}
