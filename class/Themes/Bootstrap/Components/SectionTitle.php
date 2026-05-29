<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Elements\Components\Tooltip;
use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Bootstrap\Concerns\RendersText;
use Wonder\Themes\Concerns\HasAttributes;

class SectionTitle extends Component
{
    use CanSpanColumn, RendersText, HasAttributes;

    private const ALLOWED_TAGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    public function render($class): string
    {
        $schema = $class->getSchema();
        $label = $this->escapeText(trim($class->getText()));
        $tag = strtolower((string) ($schema['level'] ?? 'h6'));
        $classes = $this->textClasses($schema);
        $classSpanColumn = $this->getColumnSpan($class->columnSpan);
        $attributes = $this->renderAttributes($schema['attributes'] ?? null);

        if (!in_array($tag, self::ALLOWED_TAGS, true)) {
            $tag = 'h6';
        }

        $tooltipText = trim((string) ($schema['tooltip'] ?? ''));
        $tooltip = $tooltipText !== ''
            ? Tooltip::make($tooltipText)->render()
            : '';

        $classAttr = $this->buildClassAttribute($classes);

        $html = "<div class=\"{$classSpanColumn}\">";
        $html .= "<{$tag}{$classAttr} {$attributes}>{$label}{$tooltip}</{$tag}>";
        $html .= '</div>';

        return $html;
    }
}
