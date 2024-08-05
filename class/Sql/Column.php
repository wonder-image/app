<?php

    namespace Wonder\Sql;

    class Column {

        public $name;
        public $definition = [
            'type' => 'VARCHAR',
            'null' => true
        ];

        public function __construct( string $name ) {
            $this->name = $name;
        }

        /**
         * Tipo valore
         * 
         * @param string $type
         * @return \Wonder\Sql\Column
         */
        public function type( string $type ): Column
        { 

            $this->definition['type'] = strtoupper($type);
            
            return $this; 
        
        }

        /**
         * Massima lunghezza valore
         * 
         * @param int $length
         * @return \Wonder\Sql\Column
         */
        public function length( int $length ): Column
        { 
            
            $this->definition['length'] = $length;

            return $this; 
        
        }

        /**
         * Accetta i valori NULL
         * 
         * @param bool $null
         * @return \Wonder\Sql\Column
         */
        public function null( bool $null = true ): Column
        { 
            
            $this->definition['null'] = $null;

            return $this; 
        
        }

        /**
         * Valore di DEFAULT
         * 
         * @param string $default
         * @return \Wonder\Sql\Column
         */
        public function default( string $default ): Column
        {
            
            $this->definition['default'] = $default;

            return $this;

        }

        /**
         * Tabella e colonna di riferimento
         * 
         * @param string $table
         * @param string $column
         * @return \Wonder\Sql\Column
         */
        public function foreign( string $table, string $column = 'id' ): Column
        {

            $this->definition['foreign_table'] = $table;
            $this->definition['foreign_key'] = $column;

            return $this;

        }

        /**
         * Crea un indice nella colonna
         * 
         * @param string|array $column
         * @return \Wonder\Sql\Column
         */
        public function index( string | array $column ): Column
        {

            $this->definition['index'] = $column;

            return $this;

        }

        public function label( string $label ): Column
        {

            $this->definition['label'] = $label;

            return $this;

        }

        public function show( bool $show = true ): Column
        {

            $this->definition['show'] = $show;

            return $this;

        }

    }
