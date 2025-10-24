<?php
    
    namespace Wonder\Elements\Table;

    use Wonder\Elements\Component;

    abstract class Column extends Component {

        public string $name;

        public function __construct( string $name ) 
        {

            $this->name = $name;

            $this->schema['name'] = $this->name;
            
        }

        public function label( string $label ): self
        { 

            return $this->schema('label', $label);
        
        }

        public function class( string $class ): self
        { 

            return $this->schema('class', $class);
        
        }

        public function size( string $size = 'little' ): self
        { 

            if (in_array($size, [ 'auto', 'little', 'medium', 'big' ])) {
                throw new \Exception("Size può essere 'auto', 'little', 'medium', 'big'.");
            }

            return $this->schema('size', $size);
            
        }

        public function hiddenDevice( string $device = 'mobile' ): self
        { 

            if (in_array($device, [ 'mobile', 'tablet', 'desktop' ])) {
                throw new \Exception("Hidden device può essere 'mobile', 'tablet', 'desktop'.");
            }

            return $this->schema('hidden-device', $device);
            
        }

        public function sortable( bool $sortable = true):self 
        {

            return $this->schema('sortable', $sortable);
            
        }

        public function callback($callback): self
        {

            return $this->schema('callback', $callback);
            
        }

        public function function($name, $parameter = 'id', $return = null): self
        {

            return $this->schema('function', [
                'name' => $name,
                'parameter' => $parameter,
                'return' => $return
            ]);

        }

        public function link($link): self 
        {

            return $this->schema('link', $link);

        }

        public function columns( array $columns ): self
        {

            return $this->schema('value', $columns);

        }

    }