<?php
    
    namespace Wonder\Elements\Form\Components;

    use Wonder\Plugin\Custom\String\Rand;

    abstract class Schema {

        public string $id, $name, $type, $value = '';
        public array $schema = [];

        public function __construct( string $name ) 
        {

            $this->name = $name;
            $this->id = strtolower((new Rand('letters'))::generate( 10, 'field_' ));
            
            $this->schema['id'] = $this->id;
            $this->schema['name'] = $this->name;
            $this->schema['type'] = $this->type;
            $this->schema['value'] = $this->value;
            $this->schema['attributes'] = [
                "data-wi-check" => "true"
            ];
            
        }

        public function schema(string $key, $value): self
        {

            $this->schema[$key] = $value;

            return $this; 

        }

        public function attr(string $key, $value):self
        {

            $this->schema['attributes'][$key] = $value;

            return $this; 

        }


        public function removeAttr(string $key):self
        {

            if (isset($this->schema['attributes'][$key])) { unset($this->schema['attributes'][$key]); }

            return $this; 

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

        public function autocomplete(string $autocomplete):self 
        {

            return $this->attr('autocomplete', $autocomplete); 

        }

        public function class(string $class): self
        { 

            return $this->attr('class', $class); 
        
        }

        public function id(string $id): self
        { 
            
            return $this->schema('id', $id); 
        
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