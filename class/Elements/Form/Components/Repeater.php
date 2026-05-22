<?php
    
    namespace Wonder\Elements\Form\Components;

    use Wonder\Elements\Form\Field;

    class Repeater extends Field {

        public string $type = 'repeater';

        public function columns(array $columns): self
        {

            return $this->schema('columns', $columns);

        }

        public function context(array $context): self
        {

            return $this->schema('context', $context);

        }

        protected function renderInput(): string {

            return '';
            
        }

    }
