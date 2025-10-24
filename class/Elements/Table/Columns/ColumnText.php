<?php
    
    namespace Wonder\Elements\Table\Columns;

    use Wonder\Elements\Table\Column;

    class ColumnText extends Column {

        public string $type = 'text'; 

        public function date( bool $date = true ): self  
        {

            $this->type = $date ? 'date' : 'text';
            
            return $this;
            
        }
        
        public function phone( bool $phone = true ): self 
        {

            $this->type = $phone ? 'phone' : 'text';
            
            return $this;

        }
        
        public function price( bool $price = true ): self 
        {

            $this->type = $price ? 'price' : 'text';

            return $this;
            
        }

    }