<?php
    
    namespace Wonder\Elements\Form\Components;

    use Wonder\Elements\Form\Components\InputPercentige;

    class InputPrice extends InputPercentige {

        public function __construct( string $name ) 
        {

            parent::__construct($name);
            
            $this->removeAttr('data-wi-number');
            $this->attr('data-wi-price', 'true');
            

        }

    }