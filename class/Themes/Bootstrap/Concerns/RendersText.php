<?php

namespace Wonder\Themes\Bootstrap\Concerns;

use Wonder\Themes\Concerns\EscapesHtml;

trait RendersText
{
    use EscapesHtml;

    protected function textClasses(array $schema): array
    {
        $classes = [];

        if (($schema['muted'] ?? false) === true) {
            $classes[] = 'text-body-secondary';
        }

        if (($schema['small'] ?? false) === true) {
            $classes[] = 'small';
        }

        if (($schema['bold'] ?? false) === true) {
            $classes[] = 'fw-bold';
        }

        if (($schema['italic'] ?? false) === true) {
            $classes[] = 'fst-italic';
        }

        $color = trim((string) ($schema['color'] ?? ''));
        if ($color !== '') {
            $classes[] = 'text-' . $color;
        }

        $align = trim((string) ($schema['align'] ?? ''));
        if ($align !== '') {
            $classes[] = 'text-' . $align;
        }

        return $classes;
    }

    protected function escapeText(string $text): string
    {
        return $this->escape($text);
    }

    protected function buildClassAttribute(array $classes): string
    {
        if ($classes === []) {
            return '';
        }

        return ' class="' . $this->escape(implode(' ', $classes)) . '"';
    }
}
