<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\ColumnSpanClasses;
use Wonder\Themes\Concerns\RendersCode;

class Code extends Component
{
    use ColumnSpanClasses, RendersCode;

    public function render($class): string
    {
        return $this->renderCode($class);
    }
}
