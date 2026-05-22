<?php
    
    namespace Wonder\Elements\Form\Components;

    use Wonder\Elements\Form\Field;

    class CheckGroup extends Field {

        public string $type = 'checkbox';

        public function options(array $options): self
        {

            return $this->schema('options', $options);

        }

        public function searchBar(bool $searchBar = true): self
        {

            return $this->schema('search_bar', $searchBar);

        }

        public function inputType(string $type): self
        {

            return $this->schema('type', $type);

        }

        protected function renderInput(): string {

            return '';
            
        }

    }
