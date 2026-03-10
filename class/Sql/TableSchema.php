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

            /**
             * Colonna ENUM con lista valori ammessi.
             *
             * @param array<int|string, mixed> $values
             * @return TableSchema
             */
            public function enum(array $values): self
            {

                $cleanValues = [];

                foreach ($values as $value) {
                    if (!is_string($value) && !is_numeric($value)) {
                        continue;
                    }

                    $value = trim((string) $value);

                    if ($value !== '') {
                        $cleanValues[] = $value;
                    }
                }

                return $this->type('ENUM')->schema('enum', array_values(array_unique($cleanValues)));

            }

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

        /**
         * Vincolo UNIQUE su singola colonna o su più colonne.
         *
         * Esempi:
         * - unique() -> UNIQUE sulla colonna corrente
         * - unique(['user_id', 'consent_type']) -> UNIQUE composto
         *
         * @param bool|string|array $columns
         * @return TableSchema
         */
        public function unique( bool | string | array $columns = true ): self
        {

            return $this->schema('unique', $columns);

        }

        /**
         * Vincolo PRIMARY KEY su singola colonna o composto.
         *
         * Esempi:
         * - primary() -> PRIMARY KEY sulla colonna corrente
         * - primary(['user_id', 'consent_type']) -> PRIMARY KEY composta
         *
         * @param bool|array $columns
         * @return TableSchema
         */
        public function primary( bool | array $columns = true ): self
        {

            return $this->schema('primary', $columns);

        }

    }
