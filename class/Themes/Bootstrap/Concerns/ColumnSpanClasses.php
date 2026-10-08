<?php

namespace Wonder\Themes\Bootstrap\Concerns;

use Wonder\Themes\Concerns\RendersColumnSpan;

/**
 * La colonna opzionale del backend: `col-span-N`, dove N è l'ultimo span
 * dichiarato dal più piccolo al più grande dei breakpoint.
 */
trait ColumnSpanClasses
{
    use RendersColumnSpan;

    protected function columnSpanClasses(array $span): string
    {
        $value = $this->lastColumnSpan(
            $span,
            ['default', 'sm', 'md', 'lg', 'xl', '2xl']
        );

        return 'col-span-'.$value;
    }
}
