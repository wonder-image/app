<?php

    namespace Wonder\Data\Fields;
    
    class Password extends Field {

        public string $type = 'password';

        public function __construct($key)
        {

            parent::__construct($key);

            $this->formatters([
                new \Wonder\Data\Formatters\String\TrimFormatter()
            ]);

        }
        
    }