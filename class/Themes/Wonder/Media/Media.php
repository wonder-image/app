<?php

namespace Wonder\Themes\Wonder\Media;

use Wonder\Themes\Concerns\RendersColumnSpan;
use Wonder\Themes\Wonder\Component;

abstract class Media extends Component
{
    use RendersColumnSpan;

    public function render($class): string
    {
        return $this->wrapColumnSpan($class, $this->renderMedia($class));
    }

    abstract protected function renderMedia($class): string;

    protected function columnSpanClasses(array $span): string
    {
        $phone = $this->lastColumnSpan($span, ['default']);
        $tablet = $this->lastColumnSpan($span, ['default', 'sm', 'md'], $phone);
        $desktop = $this->lastColumnSpan(
            $span,
            ['default', 'sm', 'md', 'lg', 'xl', '2xl'],
            $tablet
        );

        $classes = ['col-' . $desktop];

        if ($tablet !== $desktop) {
            $classes[] = 'col-t-' . $tablet;
        }

        if ($phone !== $tablet) {
            $classes[] = 'col-p-' . $phone;
        }

        return implode(' ', $classes);
    }
}
