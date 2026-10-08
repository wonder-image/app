<?php

namespace Wonder\Themes\Wonder\Concerns;

use Wonder\Themes\Concerns\RendersColumnSpan;

/**
 * La colonna opzionale del frontend sulle utility della lib: `col-N` per il
 * desktop, `col-t-N` per il tablet e `col-p-N` per il telefono, scritte solo
 * quando cambiano.
 */
trait ColumnSpanClasses
{
    use RendersColumnSpan;

    protected function columnSpanClasses(array $span): string
    {
        $phone = $this->lastColumnSpan($span, ['default']);
        $tablet = $this->lastColumnSpan($span, ['default', 'sm', 'md'], $phone);
        $desktop = $this->lastColumnSpan(
            $span,
            ['default', 'sm', 'md', 'lg', 'xl', '2xl'],
            $tablet
        );

        $classes = ['col-'.$desktop];

        if ($tablet !== $desktop) {
            $classes[] = 'col-t-'.$tablet;
        }

        if ($phone !== $tablet) {
            $classes[] = 'col-p-'.$phone;
        }

        return implode(' ', $classes);
    }
}
