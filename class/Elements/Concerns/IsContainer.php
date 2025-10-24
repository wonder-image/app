<?php

    namespace Wonder\Elements\Concerns;

    use Wonder\Elements\Concerns\{ HasColumns, CanSpanColumn, HasGap, Renderer };

    trait IsContainer {

        use HasColumns, CanSpanColumn, HasGap, Renderer;

        public array $components = [];

        public function components(array $components):self 
        {

            $this->components = $components;

            return $this;

        }

    }