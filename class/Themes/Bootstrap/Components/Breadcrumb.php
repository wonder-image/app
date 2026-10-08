<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Concerns\RendersBreadcrumb;

class Breadcrumb extends Component
{
    use RendersBreadcrumb;

    public function render($class): string
    {
        return $this->renderBreadcrumb($class, true);
    }
}
