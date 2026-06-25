<?php
    
    namespace Wonder\Elements\Form\Components;

    use Wonder\Elements\Form\Components\InputText;

    class InputNumber extends InputText {

        public string $type = 'text';

        public function __construct( string $name ) 
        {

            parent::__construct($name);
            
            $this->attr('data-wi-number', 'true');

        }

        public function decimal(int $decimal):self 
        {

            return $this->attr('wi-number-decimal', $decimal);

        }

        public function decimalSeparator(string $separator):self 
        {

            return $this->attr('wi-number-decimal-separator', $separator);

        }

        public function groupSeparator(string $separator):self 
        {

            return $this->attr('wi-number-group-separator', $separator);

        }

        public function symbol(string $symbol):self 
        {

            return $this->attr('wi-number-symbol', $symbol);

        }

        public function symbolPlacement(string $placement):self 
        {

            if (!in_array($placement, [ 's', 'p'])) {
                throw new \Exception("Placement può essere p o s [ p = prefix, s = suffix ].");
            }

            return $this->attr('wi-number-symbol', $placement);

        }

        public function decimals(int $decimals):self 
        {

            return $this->schema('decimals', $decimals);

        }

    }