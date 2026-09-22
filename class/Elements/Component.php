<?php


    namespace Wonder\Elements;

    use Wonder\Concerns\HasSchema;
    use Wonder\Elements\Concerns\HasAttributes;
    use Wonder\Elements\Concerns\HasStyle;

    abstract class Component {

        use HasSchema, HasAttributes, HasStyle;

        public string $id;
        public array $schema = [];

        public function class(string $class): self
        { 

            return $this->attr('class', [ $class ]); 
        
        }

        public function addClass(string $class): self
        { 

            return $this->pushAttr('class', $class); 
        
        }

        public function id(string $id): self
        { 
            
            $this->id = $id;
            
            return $this->schema('id', $id); 
        
        }

        /**
         * Mostra il componente solo quando un campo del form vale uno dei
         * valori dati.
         *
         * È la stessa regola che gli `Input` hanno da sempre, portata su un
         * riquadro intero: un interruttore "questo articolo ha varianti?"
         * deve poter aprire e chiudere tutto il blocco che lo riguarda, non
         * una casella alla volta. Il blocco si marca come contenitore, così
         * il JS nasconde esattamente lui e non un suo genitore.
         *
         * @param string|array<int, string> $values
         */
        public function visibleWhen(string $field, string|array $values): static
        {

            return $this->conditionalVisibility('visible', $field, $values);

        }

        /**
         * L'inverso di {@see visibleWhen()}: nasconde il componente quando il
         * campo vale uno dei valori dati.
         *
         * @param string|array<int, string> $values
         */
        public function hiddenWhen(string $field, string|array $values): static
        {

            return $this->conditionalVisibility('hidden', $field, $values);

        }

        /**
         * @param string|array<int, string> $values
         */
        private function conditionalVisibility(string $mode, string $field, string|array $values): static
        {

            $field = trim($field);

            if ($field === '') { return $this; }

            $list = implode(',', array_map(
                static fn ($value): string => trim((string) $value),
                is_array($values) ? $values : [$values]
            ));

            return $this
                ->attr('data-'.$mode.'-when', $field)
                ->attr('data-'.$mode.'-when-values', $list)
                ->attr('data-wi-conditional-container', 'true');

        }

        public function getValue()
        {

            return $this->schema['value'] ?? '';

        }

        public function toArray(): array
        {

            return $this->schema;

        }

    }
