<?php

    namespace Wonder\Sql;

    use Wonder\Sql\Query;
    use Wonder\Sql\Utility\Error;

    use Wonder\Sql\Connection;

    use mysqli;

    class CreateTable {

        public mysqli $mysqli;

        public string $ENGINE = "InnoDB";
        public string $CHARSET = "latin1";
        public string $DEFAULT_TYPE = "VARCHAR";
        public string $DEFAULT_NULL = "NOT NULL";
        public int $DEFAULT_LENGHT_VARCHAR = 1000;
        public int $DEFAULT_LENGHT_BIGINT = 11;
        public int $DEFAULT_LENGHT_INT = 11;

        function __construct( $connection = null ) {

            $this->mysqli = ($connection === null) ? Connection::Connect() : $connection;

        }

        private function TableColumn( $name, $options, $action = false ) {

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
            $type .= ($length == null) ? '': "($length)";

            $null = $options['null'] = true ? "NULL" : "NOT NULL";
            $default = empty($options['default']) ? '' : "DEFAULT '".$options['default']."'";
            $after = empty($options['after']) ? '' : "AFTER `".$options['after']."`";

            if (!empty($options['foreign_table'])) {

                $foreignTable = $options['foreign_table'];
                $foreignKey = empty($options['foreign_key']) ? "id" : $options['foreign_key'];

                $foreign = ", ";

                if ($action == 'add') {
                    $foreign .= "ADD ";
                } else if ($action == 'modify') {
                    $foreign .= "ADD ";
                }

                $foreign .= "FOREIGN KEY (`$name`) REFERENCES `$foreignTable` (`$foreignKey`) ON UPDATE CASCADE ON DELETE SET NULL ";
                
            } else {

                $foreign = "";

            }

            $return = ($action == 'add') ? "ADD " : "";
            $return .= "`$name` $type $null $default $after $foreign";
            
            return $return;
            
        }

        public function Table( $name, $columns ) {

            $SQL = new Query($this->mysqli);

            $name = strtolower($name);

            if ($SQL->TableExists($name)) {
                
                $query = "";
                $columnBefore = "id";

                foreach ($columns as $columnName => $options) {

                    $columnName = strtolower($columnName);

                    if ($SQL->ColumnExists($name, $columnName)) {
                        
                        # Elimino tutte le FOREIGN KEY
                        foreach ($SQL->ColumnForeign($name, $columnName) as $key => $foreignKey) { $query .= "DROP CONSTRAINT `$foreignKey`, "; }

                        # Modifica la colonna
                        $query .= 'MODIFY COLUMN '.$this->TableColumn( $columnName, $options, 'modify' ).', ';

                    } else {

                        $options['after'] = empty($options['after']) ? $columnBefore : $options['after'];
                        $query .= $this->TableColumn( $columnName, $options, 'add' ).', ';

                    }

                    $columnBefore = $columnName;

                }

                # Elimina colonne tolte dall'array
                foreach ($SQL->TableColumn($name) as $key => $column) {
                    if (!array_key_exists($column, $columns) && !in_array($column, [ 'id', 'deleted', 'last_modified', 'creation' ])) {
                        $query .= "DROP COLUMN $column, ";
                    }
                }

                if (!empty($query)) {

                    $query = substr($query, 0, -2);
                    $query = "ALTER TABLE `$name` $query;";

                }

                # Cambia il motore
                $this->mysqli->query( "ALTER TABLE `{$name}` ENGINE = {$this->ENGINE}" );

                # Cambia il set di caratteri
                $this->mysqli->query( "ALTER TABLE `{$name}` DEFAULT CHARSET = {$this->CHARSET}" );

                # Ottimizza la tabella
                $this->mysqli->query( "OPTIMIZE TABLE `$name`;" );
                
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
                $query .= "PRIMARY KEY (`id`), ";
                $query .= "INDEX `ind_id` (`id`) ";
                $query .= ") ";
                $query .= "ENGINE = ".$this->ENGINE." ";
                $query .= "DEFAULT CHARSET = ".$this->CHARSET." ";
                $query .= ";";

            }

            if (!empty($query)) {

                if ($this->mysqli->query( $query )) {

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