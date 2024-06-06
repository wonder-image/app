<?php

    namespace Wonder\Sql;

    use Wonder\Sql\Query;
    use Wonder\Sql\Utility\Error;

    class CreateTable {

        public object $mysqli;

        public string $ENGINE = "MyISAM";
        public string $CHARSET = "latin1";
        public string $DEFAULT_TYPE = "VARCHAR";
        public string $DEFAULT_NULL = "NOT NULL";
        public int $DEFAULT_LENGHT_VARCHAR = 1000;
        public int $DEFAULT_LENGHT_BIGINT = 11;
        public int $DEFAULT_LENGHT_INT = 11;

        function __construct( $connection ) { $this->mysqli = $connection; }

        private function TableColumn( $name, $options ) {

            $defaultLenght = null;

            $defaultType = ($name == 'position') ? 'INT' : $this->DEFAULT_TYPE;

            $type = empty($options['type']) ? $defaultType : strtoupper($options['type']);
        
            if ($type == "VARCHAR") { 
                $defaultLenght = $this->DEFAULT_LENGHT_VARCHAR; 
            } elseif ($type == "BIGINT") { 
                $defaultLenght = $this->DEFAULT_LENGHT_BIGINT; 
            } elseif ($type == "INT") {
                $defaultLenght = $this->DEFAULT_LENGHT_INT; 
            } else {
                $defaultLenght = null;
            }

            $length = empty($options['length']) ? $defaultLenght : $options['length'];
            $null = $options['null'] = true ? "NULL" : "NOT NULL";
            $default = empty($options['default']) ? '' : "DEFAULT '".$options['default']."'";
            
            if ($length == null) {
                return "`$name` $type $null $default";
            } else {
                return "`$name` $type($length) $null $default";
            }
            
        }

        public function Table( $name, $columns ) {

            $SQL = new Query($this->mysqli);

            $name = strtolower($name);

            if ($SQL->TableExists($name)) {
                
                $query = "";
                $columnBefore = "id";

                foreach ($columns as $columnName => $options) {

                    $columnName = strtolower($columnName);

                    // Cambia colonna
                    // Elimina colonna se non c'è nell'array
                    
                    if (!$SQL->ColumnExists($name, $columnName)) {

                        $columnName = strtolower($columnName);
                        $query .= 'ADD '.$this->TableColumn( $columnName, $options ).'AFTER `'.$columnBefore.'`, ';
                        
                    }

                    $columnBefore = $columnName;

                }

                if (!empty($query)) {

                    $query = substr($query, 0, -2);
                    $query = "ALTER TABLE `$name` $query";

                }
                
            } else {

                $query = "CREATE TABLE IF NOT EXISTS `$name` ";
                $query .= "( ";
                $query .= "`id` INT NOT NULL AUTO_INCREMENT, ";

                foreach ($columns as $columnName => $options) {
                    
                    $columnName = strtolower($columnName);
                    $query .= $this->TableColumn( $columnName, $options ).', ';

                }

                $query .= "`deleted` VARCHAR(5) NOT NULL DEFAULT 'false', ";
                $query .= "`last_modified` DATETIME on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ";
                $query .= "`creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ";
                $query .= "PRIMARY KEY (`id`) ";
                $query .= ") ";
                $query .= "ENGINE = ".$this->ENGINE." ";
                $query .= "DEFAULT CHARSET = ".$this->CHARSET." ";
                $query .= ";";

            }

            if (!empty($query)) {

                if($this->mysqli->query( $query )) {

                    $RETURN =  (object) array();
                    $RETURN->table = $name;
                    $RETURN->query = $query;
                    
                    return $RETURN;
                    
                } else {

                    new Error( 'Table', $name, $query, $this->mysqli );
        
                }

            }

        }

    }