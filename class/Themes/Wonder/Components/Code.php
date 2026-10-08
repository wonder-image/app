<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Concerns\RendersCode;
use Wonder\Themes\Wonder\Component;
use Wonder\Themes\Wonder\Concerns\ColumnSpanClasses;

class Code extends Component
{
    use ColumnSpanClasses, RendersCode;

    public function render($class): string
    {
        return $this->renderCode($class);
    }
}
