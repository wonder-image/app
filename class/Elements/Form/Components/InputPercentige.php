<?php
    
    namespace Wonder\Elements\Form\Components;

    use Wonder\Elements\Form\Components\InputNumber;

    class InputPercentige extends InputNumber {

        public function __construct( string $name ) 
        {

            parent::__construct($name);
            
            $this->removeAttr('data-wi-number');
            $this->attr('data-wi-percentige', 'true');

        }

    }