<?php

    namespace Wonder\Sql;

    use Wonder\Sql\{ Connection, Query };
    use Wonder\Sql\Utility\Error;

    use mysqli;

    class CreateTable {

        public mysqli $mysqli;
        public bool $debug = false;

        public string $ENGINE = "InnoDB";
        public string $CHARSET = "latin1";
        public string $DEFAULT_TYPE = "VARCHAR";
        public int $DEFAULT_LENGHT_VARCHAR = 1000;
        public int $DEFAULT_LENGHT_BIGINT = 18;
        public int $DEFAULT_LENGHT_INT = 10;
        public $DEFAULT_LENGHT_DECIMAL = '10,2';

        public array $SQL_VAR = [ 'PRIMARY KEY', 'CURRENT_TIMESTAMP', 'AUTO_INCREMENT' ];

        public function __construct( ?mysqli $connection = null ) {

            $this->mysqli = ($connection === null) ? Connection::Connect() : $connection;

        }
        
        public function debug(bool $debug = true): void 
        {
            $this->debug = $debug;
        }

        private function TableColumn( $name, $options, $action = false ) {

            if (isset($options['index']) && !empty($options['index'])) {

                # Se bisogna creare un INDEX
                
                $index = $options['index'];

                $return = "";

                if ($action == 'add' || $action == 'modify') {
                    $return .= "ADD ";
                }

                $return .= "INDEX `$name` (";

                if (is_array($index)) {

                    foreach ($index as $column) { $return .= "`$column`, "; }
                    $return = substr($return, 0, -2);

                } else {

                    $return .= "`$index`";

                }

                $return .= ") ";

            } else {

                # Se bisogna creare una colonna

                $defaultLenght = null;

                $defaultType = ($name == 'position') ? 'INT' : $this->DEFAULT_TYPE;

                $type = empty($options['type']) ? $defaultType : strtoupper($options['type']);
            
                if ($type == "VARCHAR") { 
                    $defaultLenght = $this->DEFAULT_LENGHT_VARCHAR; 
                } elseif ($type == "BIGINT") { 
                    $defaultLenght = $this->DEFAULT_LENGHT_BIGINT; 
                } elseif ($type == "INT") {
                    $defaultLenght = $this->DEFAULT_LENGHT_INT; 
                }  elseif ($type == "DECIMAL") {
                    $defaultLenght = $this->DEFAULT_LENGHT_DECIMAL; 
                } else {
                    $defaultLenght = null;
                }

                $length = empty($options['length']) ? $defaultLenght : $options['length'];

                $type .= ($length == null) ? '': "($length)";

                $after = empty($options['after']) ? '' : "AFTER `".$options['after']."`";

                # NULL || NOT NULL
                    if (isset($options['null'])) {
                        if ($options['null'] == true) {
                            $null = "NULL";
                        } else if ($options['null'] == false) {
                            $null = "NOT NULL";
                        }
                    } else {
                        $null = "NULL";
                    }

                # AUTO INCREMENT
                    if (isset($options['auto_increment']) && $options['auto_increment'] == true) {
                        $autoIncrement = "AUTO_INCREMENT";
                    } else {
                        $autoIncrement = "";
                    }

                # DEFAULT 
                    if (isset($options['default'])) {

                        $defaultValue = $options['default'];

                        $default = "DEFAULT ";

                        if (in_array($defaultValue, $this->SQL_VAR)) {
                            $default .= $defaultValue;
                        } else {
                            $default .= "'$defaultValue'";
                        }

                    } else {

                        $default = "";

                    }

                # On Update
                    if (isset($options['on_update'])) {

                        $onUpdateValue = $options['on_update'];

                        $onUpdate = 'ON UPDATE ';

                        if (in_array($onUpdateValue, $this->SQL_VAR)) {
                            $onUpdate .= $onUpdateValue;
                        } else {
                            $onUpdate .= "'$onUpdateValue'";
                        }

                    } else {

                        $onUpdate = "";

                    }

                # FOREIGN KEY
                    if (isset($options['foreign_table']) && !empty($options['foreign_table'])) {

                        $foreignTable = $options['foreign_table'];
                        $foreignKey = empty($options['foreign_key']) ? "id" : $options['foreign_key'];

                        $foreign = ", ";

                        if ($action == 'add' || $action == 'modify') {
                            $foreign .= "ADD ";
                        }

                        $foreign .= "FOREIGN KEY (`$name`) REFERENCES `$foreignTable` (`$foreignKey`) ON UPDATE CASCADE ON DELETE SET NULL ";
                        
                    } else {

                        $foreign = "";

                    }

                # PRIMARY KEY
                    if (isset($options['primary']) && $options['primary'] == true) {

                        $primary = ", ";

                        if ($action == 'add' || $action == 'modify') {
                            $primary .= "ADD ";
                        }

                        $primary .= "PRIMARY KEY (`$name`) ";

                    } else {

                        $primary = "";

                    }

                #

                if ($action == 'add') {
                    $return = "ADD ";
                } else if ($action == 'modify') {
                    $return = "MODIFY COLUMN ";
                } else {
                    $return = "";
                }

                $return .= "`$name` $type $onUpdate $null $autoIncrement $default $after $foreign $primary";

            }
            
            return $return;
            
        }

        public function Table( $name, $columns ) {

            $SQL = new Query($this->mysqli);

            $name = strtolower($name);

            $beforeColumns = [
                'id' => [
                    'type'=> 'INT',
                    'primary'=> true,
                    'null' => false,
                    'auto_increment' => true
                ]
            ];

            $afterColumns = [
                'deleted'=> [
                    'length'=> 5,
                    'null' => false,
                    'default' => 'false',
                ],
                'last_modified' => [
                    'type'=> 'DATETIME',
                    'null' => false,
                    'on_update' => 'CURRENT_TIMESTAMP',
                    'default' => 'CURRENT_TIMESTAMP',
                ],
                'creation' => [
                    'type'=> 'DATETIME',
                    'null' => false,
                    'default' => 'CURRENT_TIMESTAMP',
                ],
                'ind_id' => [
                    'index'=> 'id'
                ]
            ];

            $columns = array_merge($beforeColumns, $columns, $afterColumns);

            if ($SQL->TableExists($name)) {
                
                $query = "";
                $columnBefore = "id";

                # Elimino tutte le FOREIGN KEY
                    foreach ($SQL->ColumnForeign($name) as $key => $foreignKey) { 

                        if ($foreignKey != "PRIMARY") {
                            $query .= "DROP CONSTRAINT `$foreignKey`, "; 
                        }
                    
                    }

                # Elimino tutti gli INDEX
                    foreach ($SQL->TableIndex($name) as $key => $indexName) { 
                        
                        $query .= "DROP "; 
                        $indexName = ($indexName == 'PRIMARY') ? "PRIMARY KEY" : "$indexName";
                        $query .= in_array($indexName, $this->SQL_VAR) ? $indexName : "INDEX `$indexName`"; 
                        $query .= ", "; 
                    
                    }

                # Modifico o aggiungo le colonne
                    foreach ($columns as $columnName => $options) {

                        $columnName = strtolower($columnName);
                        
                        if ($SQL->ColumnExists($name, $columnName)) {
                            
                            # Modifica la colonna
                            $query .= $this->TableColumn( $columnName, $options, 'modify' ).', ';

                        } else {

                            # Aggiungo la colonna
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

                # Creo la query
                    if (!empty($query)) {

                        $query = substr($query, 0, -2);
                        $query = "ALTER TABLE `$name` $query;";

                    }

                #

                # Cambia il motore
                $this->mysqli->query( "ALTER TABLE `{$name}` ENGINE = {$this->ENGINE}" );

                # Cambia il set di caratteri
                $this->mysqli->query( "ALTER TABLE `{$name}` DEFAULT CHARSET = {$this->CHARSET}" );

                # Ottimizza la tabella
                $this->mysqli->query( "OPTIMIZE TABLE `$name`;" );
                
            } else {

                $query = "CREATE TABLE IF NOT EXISTS `$name` ";
                $query .= "( ";

                foreach ($columns as $columnName => $options) {
                    
                    $columnName = strtolower($columnName);
                    $query .= $this->TableColumn( $columnName, $options ).', ';

                }

                $query = substr($query, 0, -2);

                $query .= ") ";
                $query .= "ENGINE = ".$this->ENGINE." ";
                $query .= "DEFAULT CHARSET = ".$this->CHARSET." ";
                $query .= ";";

            }

            if (!empty($query)) {

                if ($this->debug) {

                    echo str_replace(", ", ",<br>", $query);

                } else {

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

    }