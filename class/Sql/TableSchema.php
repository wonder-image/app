<?php

    namespace Wonder\Sql;

    use Wonder\Concerns\HasSchema;

    class TableSchema {

        use HasSchema;

        public $name;

        public function __construct( string $name ) {

            $this->name = $name;

            $this->varchar()
                 ->null();

        }

        public static function key( string $name ): TableSchema 
        {

            return new self($name);

        }

        public function type( string $type ): self
        { 

            return $this->schema('type', strtoupper($type));
        
        }

        # Tipologie di colonna
            public function int(): self { return $this->type('INT'); }

            public function varchar(): self { return $this->type('VARCHAR'); }

            public function bool(): self { return $this->type('BOOL'); }

            public function json(): self { return $this->type('JSON'); }

            public function date(): self { return $this->type('DATE'); }

            public function datetime(): self { return $this->type('DATETIME'); }

            public function time(): self { return $this->type('TIME'); }

            public function float(): self { return $this->type('FLOAT'); }

        #

        public function length( int $length ): self
        { 

            return $this->schema('length', $length);
        
        }

        public function null( bool $null = true ): self
        { 
    
            return $this->schema('null', $null);
        
        }

        /**
         * Valore di DEFAULT
         * 
         * @param string $default
         * @return TableSchema
         */
        public function default( string $default ): self
        {
            
            return $this->schema('default', $default);

        }

        /**
         * Tabella e colonna di riferimento
         * 
         * @param string $table
         * @param string $column
         * @return TableSchema
         */
        public function foreign( string $table, string $column = 'id' ): self
        {
            
            return $this->schema('foreign_table', $table)
                        ->schema('foreign_key', $column);

        }

        /**
         * Crea un indice nella colonna
         * 
         * @param string|array $column
         * @return TableSchema
         */
        public function index( string | array $column ): self
        {

            return $this->schema('index', $column);

        }

    }
