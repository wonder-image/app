<?php
    
    namespace Wonder\Elements\Form\Components;

    use Wonder\Elements\Form\Field;

    class Checkbox extends Field {

        public string $type = 'checkbox';

        public function checked(bool $checked = true): self
        {
            return $this->attr('checked', $checked);
        }

        protected function renderInput(): string {

            return '';
            
        }
        
    }
