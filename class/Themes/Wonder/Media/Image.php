<?php

    namespace Wonder\Themes\Wonder\Media;

    use Wonder\Themes\Concerns\RendersResponsiveImage;

    class Image extends Media {

        use RendersResponsiveImage;

        protected function renderMedia($class): string
        {

            return $this->renderResponsiveImage($class);

        }

        /** Classi della lib wonder-image (frontend). */
        protected function applyThemeClasses($class): void
        {

            if ($this->getSchema('fit-cover') == true) { $class->addClass('bg bg-cover'); }
            if ($this->getSchema('fit-contain') == true) { $class->addClass('bg bg-contain'); }
            if ($this->getSchema('skeleton') == true) { $class->addClass('skeleton'); }
            if ($this->getSchema('draggable') == false) { $class->addClass('no-interaction unselectable'); }

        }

    }
