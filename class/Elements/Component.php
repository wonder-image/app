<?php


    namespace Wonder\Elements;

    use Wonder\Concerns\HasSchema;
    use Wonder\Elements\Concerns\HasAttributes;

    abstract class Component {

        use HasSchema, HasAttributes;

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

        public function getValue()
        {

            return $this->schema['value'] ?? '';

        }

        public function toArray(): array
        {

            return $this->schema;

        }

    }