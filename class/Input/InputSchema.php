<?php
    
    namespace Wonder\Input;

    class InputSchema {

        public $name;
        public $schema = [];

        public function __construct( string $name ) 
        {
            $this->name = $name;
        }

        public function label( string $label ): self
        { 

            $this->schema['label'] = $label;
            
            return $this; 
        
        }

        public function value( $value ): self
        { 

            $this->schema['value'] = $value;
            
            return $this; 
        
        }

    }