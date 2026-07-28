<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Concerns\RendersComponentAttributes;
use Wonder\Themes\Concerns\RendersColumnSpan;
use Wonder\Themes\Concerns\RendersThemeComponents;
use Wonder\Themes\Wonder\Component;

class Container extends Component
{
    use RendersComponentAttributes;
    use RendersColumnSpan;
    use RendersThemeComponents;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $classes = [];

        if (($schema['no-grid'] ?? false) !== true) {
            $classes = array_merge(
                ['d-grid'],
                $this->responsiveClasses('col', $class->columns),
                $this->responsiveClasses('gap', $class->gap)
            );
        }

        $attributes = $this->renderComponentAttributes($class, $classes);
        $content = $this->renderThemeComponents($class->components, 'wonder');

        $html = '<div'.($attributes !== '' ? ' '.$attributes : '').'>'.$content.'</div>';

        return $this->wrapColumnSpan($class, $html);
    }

    protected function columnSpanClasses(array $span): string
    {
        return implode(' ', $this->responsiveClasses('col', $span));
    }

    /**
     * Traduce i valori mobile-first dell'Element nelle utility responsive Wonder.
     *
     * @param array<string, int|null> $values
     * @return string[]
     */
    private function responsiveClasses(string $prefix, array $values): array
    {
        $phone = $this->lastValue($values, ['default'], 1);
        $tablet = $this->lastValue($values, ['default', 'sm', 'md'], $phone);
        $desktop = $this->lastValue(
            $values,
            ['default', 'sm', 'md', 'lg', 'xl', '2xl'],
            $tablet
        );

        $classes = [$prefix.'-'.$desktop];

        if ($tablet !== $desktop) {
            $classes[] = $prefix.'-t-'.$tablet;
        }

        if ($phone !== $tablet) {
            $classes[] = $prefix.'-p-'.$phone;
        }

        return $classes;
    }

    /**
     * @param array<string, int|null> $values
     * @param string[] $breakpoints
     */
    private function lastValue(array $values, array $breakpoints, int $fallback): int
    {
        $value = $fallback;

        foreach ($breakpoints as $breakpoint) {
            if (isset($values[$breakpoint])) {
                $value = (int) $values[$breakpoint];
            }
        }

        return $value;
    }
}
