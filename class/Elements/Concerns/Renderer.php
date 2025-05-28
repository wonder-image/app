<?php

    namespace Wonder\Elements\Concerns;

    use Wonder\Themes\Resolver;

    trait Renderer
    {
        
        public function render(): string
        {

            $renderer = Resolver::renderer(static::class);
            return $renderer->render($this);

        }

    }