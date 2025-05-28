<?php
    
    namespace Wonder\Elements\Form\Components;

    use Wonder\Elements\Form\Field;

    class InputText extends Field {

        public string $type = 'text'; 

        
        public function __construct( string $name ) 
        {

            parent::__construct($name);
            
            $this->placeholder('');
            
        }
        
        public function placeholder($placeholder):self 
        {

            return $this->attr('placeholder', $placeholder);

        }

        public function maxLength($maxlength):self 
        {

            return $this->schema('max-length', $maxlength);

        }
        
        public function minLength($minlength):self 
        {

            return $this->schema('min-length', $minlength);

        }

        public function pattern($pattern):self 
        {

            return $this->schema('pattern', $pattern);

        }

    }