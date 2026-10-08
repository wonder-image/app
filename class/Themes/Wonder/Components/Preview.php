<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Concerns\RendersPreview;
use Wonder\Themes\Wonder\Component;
use Wonder\Themes\Wonder\Concerns\ColumnSpanClasses;

class Preview extends Component
{
    use ColumnSpanClasses, RendersPreview;

    public function render($class): string
    {
        return $this->renderPreview($class);
    }
}
