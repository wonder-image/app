<?php

    namespace Wonder\Themes\Contracts;

    interface Renderer
    {
        
        public function render(mixed $class): string;

    }