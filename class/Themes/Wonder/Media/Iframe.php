<?php

namespace Wonder\Themes\Wonder\Media;

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
            return ['bg bg-cover'];
        }

        if ($class->getSchema('fit-contain') === true) {
            return ['bg bg-contain'];
        }

        return [];
    }
}
