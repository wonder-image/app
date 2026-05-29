<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Bootstrap\Concerns\RendersText;
use Wonder\Themes\Concerns\HasAttributes;

class Text extends Component
{
    use CanSpanColumn, RendersText, HasAttributes;

    private const ALLOWED_TAGS = ['p', 'span', 'div', 'small', 'strong', 'em', 'mark', 'abbr', 'blockquote'];

    public function render($class): string
    {
        $schema = $class->getSchema();
        $text = $class->getText();
        $tag = (string) ($schema['tag'] ?? 'p');
        $classes = $this->textClasses($schema);
        $classSpanColumn = $this->getColumnSpan($class->columnSpan);
        $attributes = $this->renderAttributes($schema['attributes'] ?? null);

        if (!in_array($tag, self::ALLOWED_TAGS, true)) {
            $tag = 'p';
        }

        if (($schema['lead'] ?? false) === true) {
            $classes[] = 'lead';
        }

        $classAttr = $this->buildClassAttribute($classes);

        $html = "<div class=\"{$classSpanColumn}\">";
        $html .= "<{$tag}{$classAttr} {$attributes}>{$text}</{$tag}>";
        $html .= '</div>';

        return $html;
    }
}
