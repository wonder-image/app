<?php
    
    namespace Wonder\Elements\Form\Components;

    use Wonder\Elements\Form\Field;

    class Textarea extends Field {

        public string $type = 'textarea'; 

        protected function renderInput(): string {

            return '';
            
        }

    }