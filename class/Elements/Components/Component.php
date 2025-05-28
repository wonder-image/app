<?php

    namespace Wonder\Elements\Components;

    class Component {
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
