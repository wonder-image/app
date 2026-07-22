<?php

namespace Wonder\Themes\Concerns;

trait RendersColumnSpan
{
    protected function wrapColumnSpan(object $element, string $html): string
    {
        if (!$element->hasExplicitColumnSpan()) {
            return $html;
        }

        $classes = $this->columnSpanClasses($element->columnSpan);
        $attribute = $classes === '' ? '' : ' class="' . $this->escape($classes) . '"';

        return '<div' . $attribute . '>' . $html . '</div>';
    }

    abstract protected function columnSpanClasses(array $span): string;

    protected function lastColumnSpan(array $span, array $breakpoints, int $fallback = 1): int
    {
        $resolved = $fallback;

        foreach ($breakpoints as $breakpoint) {
            $value = $this->normalizeColumnSpan($span[$breakpoint] ?? null);

            if ($value !== null) {
                $resolved = $value;
            }
        }

        return $resolved;
    }

    private function normalizeColumnSpan(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value >= 1 && $value <= 12 ? $value : null;
    }
}
