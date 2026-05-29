<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Bootstrap\Concerns\RendersText;
use Wonder\Themes\Concerns\HasAttributes;

class HelpText extends Component
{
    use CanSpanColumn, RendersText, HasAttributes;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $text = $this->escapeText($class->getText());
        $classes = $this->textClasses($schema);
        $classSpanColumn = $this->getColumnSpan($class->columnSpan);
        $attributes = $this->renderAttributes($schema['attributes'] ?? null);

        $classAttr = $this->buildClassAttribute($classes);

        $html = "<div class=\"{$classSpanColumn}\">";
        $html .= "<div{$classAttr} {$attributes}>{$text}</div>";
        $html .= '</div>';

        return $html;
    }
}
