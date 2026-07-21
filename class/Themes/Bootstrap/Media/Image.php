<?php

    namespace Wonder\Themes\Bootstrap\Media;

    use Wonder\Themes\Bootstrap\Component;
    use Wonder\Themes\Concerns\RendersResponsiveImage;

    class Image extends Component {

        use RendersResponsiveImage;

        public function render($class): string
        {

            return $this->renderResponsiveImage($class);

        }

        /** Utility Bootstrap 5.3 (object-fit / user-select), niente classi lib. */
        protected function applyThemeClasses($class): void
        {

            if ($this->getSchema('fit-cover') == true) { $class->addClass('object-fit-cover w-100 h-100'); }
            if ($this->getSchema('fit-contain') == true) { $class->addClass('object-fit-contain w-100 h-100'); }
            if ($this->getSchema('skeleton') == true) { $class->addClass('bg-body-secondary'); }
            if ($this->getSchema('draggable') == false) {
                $class->addClass('user-select-none pe-none');
                $class->attr('draggable', 'false');
            }

        }

    }
