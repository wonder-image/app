<?php

    namespace Wonder\Elements\Concerns;

    use Wonder\Concerns\HasSchema;

    trait HasAttributes {

        use HasSchema;
        
        public function attr(string $key, $value): static
        {

            $this->schema['attributes'][$key] = $value;

            return $this; 

        }

        public function pushAttr(string $key, $value): static
        {

            if (!isset($this->schema['attributes'][$key])) {
                $this->schema['attributes'][$key] = [];
            }

            array_push($this->schema['attributes'][$key], $value);
            
            return $this; 

        }

        public function removeAttr(string $key): static
        {

            if (isset($this->schema['attributes'][$key])) { unset($this->schema['attributes'][$key]); }

            return $this; 

        }

        public function getAttr($key = null)
        {

            if ($key == null || $this->getSchema('attributes') == null || $this->getSchema('attributes')[$key] == null) {
                return null;
            }
            
            return $this->getSchema('attributes')[$key];

        }


    }