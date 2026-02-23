<?php

    namespace Wonder\Data\Fields;

    class Number extends Field {

        public string $type = 'number';
        
        public function __construct($key)
        {

            parent::__construct($key);

            $this->formatters([
                new \Wonder\Data\Formatters\String\TrimFormatter()
            ]);

        }

        public function decimals( int $decimals = 2): self
        {
            
            return $this->schema('decimals', $decimals);

        }

    }