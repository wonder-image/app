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

        /**
         * Le voci come pillole in linea invece che incolonnate in un
         * riquadro che scorre.
         */
        public function pills(bool $pills = true): self
        {

            return $this->schema('pills', $pills);

        }

        /** Di quale risorsa questo campo elenca le righe. */
        public function listsResource(string $slug): self
        {

            return $this->schema('lists_resource', $slug);

        }

        protected function renderInput(): string {

            return '';
            
        }

    }
