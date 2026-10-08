<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Concerns\RendersBreadcrumb;
use Wonder\Themes\Wonder\Component;

class Breadcrumb extends Component
{
    use RendersBreadcrumb;

    public function render($class): string
    {
        return $this->renderBreadcrumb($class, false);
    }
}
