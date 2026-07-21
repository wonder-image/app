<?php

namespace Wonder\Themes\Bootstrap\Media;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Concerns\RendersVideo;

class Video extends Component
{
    use RendersVideo;

    public function render($class): string
    {
        return $this->renderVideo($class);
    }

    protected function videoThemeClasses(object $class): array
    {
        $classes = [];

        if ($class->getSchema('fit-cover') === true) {
            $classes[] = 'object-fit-cover w-100 h-100';
        } elseif ($class->getSchema('fit-contain') === true) {
            $classes[] = 'object-fit-contain w-100 h-100';
        }

        if ($class->getSchema('fixed') === true) {
            $classes[] = 'position-fixed top-0 start-0 w-100 h-100';
        }

        return $classes;
    }

    protected function renderVideoFilter(object $class): string
    {
        if ($class->getSchema('fixed') === true) {
            $zIndex = $this->escape((string) ($class->getStyle('z-index') ?? '-1'));

            return '<div class="position-fixed top-0 start-0 w-100 h-100 bg-dark opacity-75 pe-none" style="z-index: '
                . $zIndex . ';"></div>';
        }

        return '<div class="position-absolute top-0 start-0 w-100 h-100 bg-dark opacity-75 pe-none"></div>';
    }
}
