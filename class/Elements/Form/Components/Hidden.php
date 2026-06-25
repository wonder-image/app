<?php
    
    namespace Wonder\Elements\Form\Components;

    use Wonder\Elements\Form\Field;

    class Hidden extends Field {

        public string $type = 'hidden';

        protected function renderInput(): string {

            return '';
            
        }

    }
