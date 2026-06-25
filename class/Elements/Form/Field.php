<?php
    
    namespace Wonder\Elements\Form;

    use Wonder\Elements\Component;
    use Wonder\Support\Text\Random;

    use Wonder\Elements\Concerns\{ CanSpanColumn, Renderer };

    abstract class Field extends Component {

        use CanSpanColumn, Renderer;

        public string $name, $type;
        public mixed $value = '';
        public bool $valid = true;

        public function __construct( string $name ) 
        {

            $this->name = $name;
            $this->id = strtolower((new Random('letters'))::generate(10, 'field_'));
            
            $this->schema['id'] = $this->id;
            $this->schema['name'] = $this->name;
            $this->schema['type'] = $this->type;
            $this->schema['value'] = $this->value;
            $this->schema['attributes'] = [
                "data-wi-check" => "true"
            ];
            
        }

        public function label( string $label ): self
        { 

            return $this->schema('label', $label);
        
        }

        public function value( $value ): self
        { 

            $this->value = $value;
            return $this->schema('value', $value);
        
        }

        public function error( $error ): self
        { 

            $this->valid = empty($error) ? true : false;
            return $this->schema('error', $error);
        
        }

        public function readonly($readonly = true):self 
        {

            return $this->attr('readonly', $readonly);

        }

        public function required($required = true):self 
        {

            return $this->attr('required', $required);

        }

        public function disabled($disabled = true):self 
        {

            return $this->attr('disabled', $disabled);

        }

        public function autocomplete(bool|string $autocomplete = true):self
        {
            if ($autocomplete === true) {
                $autocomplete = $this->type === 'email' ? 'email' : 'on';
            } elseif ($autocomplete === false) {
                $autocomplete = 'off';
            } elseif (trim($autocomplete) === '') {
                return $this->removeAttr('autocomplete');
            }

            return $this->attr('autocomplete', $autocomplete);

        }

        /**
         * Disattiva il pattern "floating label" per questo campo.
         *
         * Effetti theme-specific:
         *  - Wonder: aggiunge la classe `wi-nf` al `.wi-input-container`
         *    (il CSS frontend rimuove l'animazione di "galleggiamento"
         *    della label).
         *  - Bootstrap: salta il wrap `<div class="form-floating">`.
         *
         * Il default in entrambi i temi è `floating = true`. Chiama
         * `noFloating()` senza argomenti per attivarlo, o `noFloating(false)`
         * per ri-abilitare esplicitamente in casi edge.
         *
         * Se il valore è impostato anche a livello di `Form`, vince il
         * default propagato dal Form a meno che il campo NON l'abbia già
         * impostato esplicitamente (override puntuale possibile).
         */
        public function noFloating(bool $noFloating = true): self
        {

            return $this->schema('no_floating', $noFloating);

        }

    }
