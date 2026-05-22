<?php
    
    namespace Wonder\Elements\Form\Components;

    class InputColor extends InputText {

        public string $type = 'text';

        public function __construct( string $name ) 
        {

            parent::__construct($name);
            
            $this->attr('data-wi-check-color', 'true');

        }

    }
