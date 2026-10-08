<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\ColumnSpanClasses;
use Wonder\Themes\Concerns\RendersPreview;

class Preview extends Component
{
    use ColumnSpanClasses, RendersPreview;

    public function render($class): string
    {
        return $this->renderPreview($class);
    }
}
