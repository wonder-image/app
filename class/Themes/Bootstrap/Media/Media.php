<?php

namespace Wonder\Themes\Bootstrap\Media;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\ColumnSpanClasses;

abstract class Media extends Component
{
    use ColumnSpanClasses;

    public function render($class): string
    {
        return $this->wrapColumnSpan($class, $this->renderMedia($class));
    }

    abstract protected function renderMedia($class): string;
}
