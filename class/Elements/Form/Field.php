<?php
    
    namespace Wonder\Elements\Form;

    use Wonder\Elements\Component;
    use Wonder\Plugin\Custom\String\Rand;

    use Wonder\Elements\Concerns\{ CanSpanColumn, Renderer };

    abstract class Field extends Component {

        use CanSpanColumn, Renderer;

        public string $name, $type, $value = '';
        public bool $valid = true;

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

        public function autocomplete(string $autocomplete):self 
        {

            return $this->attr('autocomplete', $autocomplete); 

        }
       
    }