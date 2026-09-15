<?php

namespace Wonder\Themes\Bootstrap\Media;

use Wonder\Themes\Concerns\RendersDeferred;

class Deferred extends Media
{
    use RendersDeferred;

    protected function renderMedia($class): string
    {
        return $this->renderDeferred($class, 'bootstrap');
    }
}
