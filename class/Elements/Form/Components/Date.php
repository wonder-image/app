<?php
    
    namespace Wonder\Elements\Form\Components;

    use Wonder\Elements\Form\Field;

    class Date extends Field {

        public string $type = 'date';

        public function min(string $date): self
        {
            return $this->attr('min', $date);
        }

        public function max(string $date): self
        {
            return $this->attr('max', $date);
        }

        protected function renderInput(): string {

            return '';
            
        }
        
    }
