<?php
    
    namespace Wonder\Elements\Form\Components;

    use Wonder\Elements\Form\Components\InputText;

    class InputTel extends InputText {

        public string $type = 'tel'; 

        public function __construct( string $name ) 
        {

            parent::__construct($name);
            
            $this->attr('inputmode', 'tel');
            $this->attr('data-wi-phone', 'true');
            
        }

    }