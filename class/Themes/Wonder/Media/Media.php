<?php

namespace Wonder\Themes\Wonder\Media;

use Wonder\Themes\Wonder\Component;
use Wonder\Themes\Wonder\Concerns\ColumnSpanClasses;

abstract class Media extends Component
{
    use ColumnSpanClasses;

    public function render($class): string
    {
        return $this->wrapColumnSpan($class, $this->renderMedia($class));
    }

    abstract protected function renderMedia($class): string;
}
