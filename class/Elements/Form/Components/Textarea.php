<?php
    
    namespace Wonder\Elements\Form\Components;

    use Wonder\Elements\Form\Field;

    class Textarea extends Field {

        public string $type = 'textarea'; 

        public function rows(int $rows): self
        {
            return $this->attr('rows', max(1, $rows));
        }

        /**
         * Limita la lunghezza massima del testo. Il renderer del tema
         * (Wonder/Bootstrap) usa il valore per:
         *  - mostrare un counter overlay `<span class="wi-counter">…/…`
         *  - non aggiunge `maxlength` come attributo HTML (il counter è
         *    gestito lato JS via `data-wi-counter`).
         */
        public function maxLength(int $maxLength): self
        {
            return $this->schema('max_length', max(0, $maxLength));
        }

        /**
         * Attiva il counter JS lato client (`data-wi-counter="true"`).
         */
        public function counter(bool $counter = true): self
        {
            return $this->attr('data-wi-counter', $counter ? 'true' : 'false');
        }

        protected function renderInput(): string {

            return '';
            
        }

    }
