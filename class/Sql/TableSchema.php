<?php

    namespace Wonder\Sql;

    class TableSchema {

        public $name;
        public $schema = [
            'type' => 'VARCHAR',
            'null' => true
        ];

        public function __construct( string $name ) {
            $this->name = $name;
        }

        /**
         * Self
         * @param string $type
         * @return TableSchema
         */
        public function type( string $type ): self
        { 

            $this->schema['type'] = strtoupper($type);
            
            return $this; 
        
        }

        /**
         * Massima lunghezza valore
         * 
         * @param int $length
         * @return TableSchema
         */
        public function length( int $length ): self
        { 
            
            $this->schema['length'] = $length;

            return $this; 
        
        }

        /**
         * Accetta i valori NULL
         * 
         * @param bool $null
         * @return TableSchema
         */
        public function null( bool $null = true ): self
        { 
            
            $this->schema['null'] = $null;

            return $this; 
        
        }

        /**
         * Valore di DEFAULT
         * 
         * @param string $default
         * @return TableSchema
         */
        public function default( string $default ): self
        {
            
            $this->schema['default'] = $default;

            return $this;

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

            $this->schema['foreign_table'] = $table;
            $this->schema['foreign_key'] = $column;

            return $this;

        }

        /**
         * Crea un indice nella colonna
         * 
         * @param string|array $column
         * @return TableSchema
         */
        public function index( string | array $column ): self
        {

            $this->schema['index'] = $column;

            return $this;

        }

        public function label( string $label ): self
        {

            $this->schema['label'] = $label;

            return $this;

        }

        public function show( bool $show = true ): self
        {

            $this->schema['show'] = $show;

            return $this;

        }

    }
