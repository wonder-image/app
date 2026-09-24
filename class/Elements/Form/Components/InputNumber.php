<?php
    
    namespace Wonder\Elements\Form\Components;

    use Wonder\Elements\Form\Components\InputText;

    class InputNumber extends InputText {

        public string $type = 'text';

        public function __construct( string $name ) 
        {

            parent::__construct($name);
            
            $this->attr('data-wi-number', 'true');

            // Formato italiano di default: virgola decimale e nessun
            // separatore delle migliaia («20000,5», non «20.000,5» che si
            // leggerebbe ventimila). Chi chiama lo sovrascrive con i setter.
            $this->decimalSeparator(',');
            $this->groupSeparator('');

        }

        public function decimal(int $decimal):self
        {

            return $this->attr('data-wi-number-decimal', $decimal);

        }

        public function decimalSeparator(string $separator):self
        {

            return $this->attr('data-wi-number-decimal-separator', $separator);

        }

        public function groupSeparator(string $separator):self
        {

            return $this->attr('data-wi-number-group-separator', $separator);

        }

        public function symbol(string $symbol):self
        {

            return $this->attr('data-wi-number-symbol', $symbol);

        }

        public function symbolPlacement(string $placement):self
        {

            if (!in_array($placement, [ 's', 'p'])) {
                throw new \Exception("Placement può essere p o s [ p = prefix, s = suffix ].");
            }

            return $this->attr('data-wi-number-symbol-placement', $placement);

        }

        public function decimals(int $decimals):self 
        {

            return $this->schema('decimals', $decimals);

        }

    }