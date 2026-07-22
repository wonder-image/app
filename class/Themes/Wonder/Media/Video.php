<?php

namespace Wonder\Themes\Wonder\Media;

use Wonder\Themes\Concerns\RendersVideo;

class Video extends Media
{
    use RendersVideo;

    protected function renderMedia($class): string
    {
        return $this->renderVideo($class);
    }

    protected function videoThemeClasses(object $class): array
    {
        $classes = [];

        if ($class->getSchema('fit-cover') === true) {
            $classes[] = 'bg bg-cover';
        } elseif ($class->getSchema('fit-contain') === true) {
            $classes[] = 'bg bg-contain';
        }

        if ($class->getSchema('fixed') === true) {
            $classes[] = 'p-f w-100 h-100';

            if ($class->getSchema('fit-cover') !== true && $class->getSchema('fit-contain') !== true) {
                $classes[] = 'top start';
            }
        }

        return $classes;
    }

    protected function renderVideoFilter(object $class): string
    {
        if ($class->getSchema('fixed') === true) {
            $zIndex = $this->escape((string) ($class->getStyle('z-index') ?? '-1'));

            return '<div class="bg bg-secondary o-70 no-interaction p-f w-100 h-100" style="z-index: '
                . $zIndex . ';"></div>';
        }

        return '<div class="bg bg-secondary o-70 no-interaction"></div>';
    }
}
