<?php

    namespace Wonder\Elements\Form;

    use Wonder\Elements\Concerns\{ HasColumns, HasGap, Renderer };

    class Form {

        use HasColumns, HasGap, Renderer;

        public array $components = [];

        public function schema(array $components): self
        {
            
            $this->components($components);

            return $this;

        }

        public function components(array $components):self 
        {

            $this->components = $components;

            return $this;

        }

    }
