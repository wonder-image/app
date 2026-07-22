<?php

namespace Wonder\Themes\Bootstrap\Media;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Concerns\RendersColumnSpan;

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
        $value = $this->lastColumnSpan(
            $span,
            ['default', 'sm', 'md', 'lg', 'xl', '2xl']
        );

        return 'col-span-' . $value;
    }
}
