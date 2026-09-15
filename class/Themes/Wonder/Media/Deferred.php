<?php

namespace Wonder\Themes\Wonder\Media;

use Wonder\Themes\Concerns\RendersDeferred;

class Deferred extends Media
{
    use RendersDeferred;

    protected function renderMedia($class): string
    {
        return $this->renderDeferred($class, 'wonder');
    }
}
