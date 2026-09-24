<?php
    
    namespace Wonder\Elements\Form\Components;

    use Wonder\Elements\Form\Components\InputNumber;

    /**
     * Prezzo: formato italiano di default «1.299,90 €» (virgola decimale,
     * punto delle migliaia, « €» in coda). Estende `InputNumber` e non
     * `InputPercentige`: emette solo `data-wi-price`, così la lib non vede
     * anche un `data-wi-percentige` che col prezzo non c'entra.
     */
    class InputPrice extends InputNumber {

        public function __construct( string $name ) 
        {

            parent::__construct($name);
            
            $this->removeAttr('data-wi-number');
            $this->attr('data-wi-price', 'true');

            $this->decimalSeparator(',');
            $this->groupSeparator('.');
            $this->symbol(' €');
            $this->symbolPlacement('s');

        }

    }