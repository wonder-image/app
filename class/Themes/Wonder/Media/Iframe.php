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

    protected function expandWrapperClass(): string
    {
        return 'p-a top start w-100 h-100';
    }

    protected function expandButtonClass(): string
    {
        return 'btn btn-dark p-a top end m-2';
    }

    protected function expandLoadEvent(): string
    {
        return 'loaded';
    }
}
