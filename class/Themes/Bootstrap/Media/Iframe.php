<?php

namespace Wonder\Themes\Bootstrap\Media;

use Wonder\Themes\Concerns\RendersIframe;

class Iframe extends Media
{
    use RendersIframe;

    protected function renderMedia($class): string
    {
        return $this->renderIframe($class);
    }

    protected function iframeThemeClasses(object $class): array
    {
        if ($class->getSchema('fit-cover') === true) {
            return ['object-fit-cover w-100 h-100'];
        }

        if ($class->getSchema('fit-contain') === true) {
            return ['object-fit-contain w-100 h-100'];
        }

        return [];
    }
}
