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

        /**
         * Normalizza una lista colonne usata nei vincoli.
         *
         * @param mixed $columns
         * @param string|null $fallback
         * @return array<int, string>
         */
        private function normalizeColumnList($columns, ?string $fallback = null): array
        {

            if ($columns === true && $fallback !== null) {
                $columns = [ $fallback ];
            } elseif (is_string($columns) || is_numeric($columns)) {
                $columns = [ (string) $columns ];
            } elseif (!is_array($columns)) {
                $columns = [];
            }

            $return = [];

            foreach ($columns as $column) {
                if (!is_string($column) && !is_numeric($column)) {
                    continue;
                }

                $column = trim((string) $column);

                if ($column !== '') {
                    $return[] = $column;
                }
            }

            return array_values(array_unique($return));

        }

        /**
         * Converte una lista colonne nel formato SQL `a`, `b`.
         *
         * @param array<int, string> $columns
         * @return string
         */
        private function columnListToSql(array $columns): string
        {

            $sql = '';

            foreach ($columns as $column) {
                $sql .= "`$column`, ";
            }

            return substr($sql, 0, -2);

        }

        /**
         * Costruisce SQL per INDEX/UNIQUE/PRIMARY.
         */
        private function buildConstraintSql(string $constraintSql, bool|string $action = false): string
        {

            $return = '';

            if ($action === 'add' || $action === 'modify') {
                $return .= 'ADD ';
            }

            $return .= $constraintSql.' ';

            return $return;

        }

        /**
         * Riconosce se una definizione contiene opzioni di colonna reali
         * (non solo vincoli pseudo-colonna).
         */
        private function isColumnDefinition(array $options): bool
        {

            $columnKeys = [
                'type',
                'length',
                'null',
                'auto_increment',
                'default',
                'on_update',
                'foreign_table',
                'foreign_key',
                'foreign_on_update',
                'foreign_on_delete',
                'after',
                'enum'
            ];

            foreach ($columnKeys as $key) {
                if (array_key_exists($key, $options)) {
                    return true;
                }
            }

            return false;

        }

        /**
         * Normalizza azione referenziale per FOREIGN KEY.
         *
         * @param mixed $action
         * @param string $fallback
         * @return string
         */
        private function normalizeReferentialAction($action, string $fallback): string
        {

            $allowed = [ 'CASCADE', 'RESTRICT', 'NO ACTION', 'SET NULL' ];

            if (!is_string($action) && !is_numeric($action)) {
                return $fallback;
            }

            $action = strtoupper(trim((string) $action));
            $action = preg_replace('/\s+/', ' ', $action) ?? '';

            if ($action === '' || !in_array($action, $allowed, true)) {
                return $fallback;
            }

            return $action;

        }

        /**
         * Estrae opzioni tabella da chiave speciale "__table".
         *
         * @param array<string, mixed> $columns
         * @return array{auto_id: bool, audit_columns: bool}
         */
        private function extractTableOptions(array &$columns): array
        {

            $tableOptions = [
                'auto_id' => true,
                'audit_columns' => true,
            ];

            if (isset($columns['__table']) && is_array($columns['__table'])) {

                $config = $columns['__table'];

                if (isset($config['auto_id'])) {
                    $tableOptions['auto_id'] = (bool) $config['auto_id'];
                }

                if (isset($config['audit_columns'])) {
                    $tableOptions['audit_columns'] = (bool) $config['audit_columns'];
                }

                unset($columns['__table']);

            }

            return $tableOptions;

        }

        /**
         * Estrae vincoli (primary/unique) da schema legacy o model schema.
         *
         * @param array<string, mixed> $columns
         * @return array{primary: array<int, string>, unique: array<string, array<int, string>>}
         */
        private function extractConstraints(array &$columns): array
        {

            $constraints = [
                'primary' => [],
                'unique' => [],
            ];

            foreach ($columns as $name => $options) {

                if (!is_array($options)) {
                    continue;
                }

                $isColumnDefinition = $this->isColumnDefinition($options);

                # PRIMARY KEY composta via pseudo-colonna:
                # 'pk_name' => [ 'primary' => ['col_a', 'col_b'] ]
                if (
                    !$isColumnDefinition &&
                    isset($options['primary']) &&
                    is_array($options['primary'])
                ) {

                    $primaryColumns = $this->normalizeColumnList($options['primary']);
                    if (!empty($primaryColumns)) {
                        $constraints['primary'] = $primaryColumns;
                    }

                    unset($columns[$name]);
                    continue;

                }

                # UNIQUE composta via pseudo-colonna:
                # 'uq_name' => [ 'unique' => ['col_a', 'col_b'] ]
                if (!$isColumnDefinition && isset($options['unique'])) {

                    $uniqueColumns = $this->normalizeColumnList($options['unique']);
                    if (!empty($uniqueColumns)) {
                        $constraints['unique'][$name] = $uniqueColumns;
                    }

                    unset($columns[$name]);
                    continue;

                }

                # UNIQUE singola su colonna:
                # 'email' => [ ..., 'unique' => true ]
                if (array_key_exists('unique', $options)) {

                    $uniqueColumns = $this->normalizeColumnList($options['unique'], (string) $name);
                    if (!empty($uniqueColumns)) {
                        $constraints['unique']['uniq_'.$name] = $uniqueColumns;
                    }

                    unset($columns[$name]['unique']);

                }
            }

            return $constraints;

        }

        /**
         * Appende vincoli estratti come pseudo-colonne in coda allo schema.
         *
         * @param array<string, mixed> $columns
         * @param array{primary: array<int, string>, unique: array<string, array<int, string>>} $constraints
         * @return array<string, mixed>
         */
        private function appendConstraints(array $columns, array $constraints): array
        {

            if (!empty($constraints['primary'])) {
                $columns['__pk_composite'] = [ 'primary' => $constraints['primary'] ];
            }

            foreach ($constraints['unique'] as $name => $uniqueColumns) {
                $columns['__unique_'.$name] = [ 'unique' => $uniqueColumns ];
            }

            return $columns;

        }

        /**
         * Lista valori enum sanificata.
         *
         * @param array<string, mixed> $options
         * @return array<int, string>
         */
        private function normalizeEnumValues(array $options): array
        {

            $enumValues = $options['enum'] ?? [];

            if (is_string($enumValues)) {
                $enumValues = array_map('trim', explode(',', $enumValues));
            }

            if (!is_array($enumValues)) {
                return [];
            }

            $return = [];

            foreach ($enumValues as $value) {
                if (!is_string($value) && !is_numeric($value)) {
                    continue;
                }

                $value = trim((string) $value);

                if ($value !== '') {
                    $return[] = $value;
                }
            }

            return array_values(array_unique($return));

        }

        private function TableColumn( $name, $options, $action = false ) {

            if (isset($options['index']) && !empty($options['index'])) {

                # Se bisogna creare un INDEX
                
                $indexColumns = $this->normalizeColumnList($options['index'], $name);

                if (empty($indexColumns)) {
                    return '';
                }

                $return = $this->buildConstraintSql(
                    "INDEX `$name` (".$this->columnListToSql($indexColumns).")",
                    $action
                );

            } elseif (isset($options['unique'])) {

                # Se bisogna creare un vincolo UNIQUE (singolo o composito)
                $uniqueColumns = $this->normalizeColumnList($options['unique'], $name);

                if (empty($uniqueColumns)) {
                    return '';
                }

                $return = $this->buildConstraintSql(
                    "UNIQUE KEY `$name` (".$this->columnListToSql($uniqueColumns).")",
                    $action
                );

            } elseif (isset($options['primary']) && is_array($options['primary'])) {

                # Se bisogna creare una PRIMARY KEY composta
                $primaryColumns = $this->normalizeColumnList($options['primary']);

                if (empty($primaryColumns)) {
                    return '';
                }

                $return = $this->buildConstraintSql(
                    "PRIMARY KEY (".$this->columnListToSql($primaryColumns).")",
                    $action
                );

            } else {

                # Se bisogna creare una colonna

                $defaultLenght = null;

                $defaultType = ($name == 'position') ? 'INT' : $this->DEFAULT_TYPE;

                $type = empty($options['type']) ? $defaultType : strtoupper((string) $options['type']);
            
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

                if ($type == "ENUM") {

                    $enumValues = $this->normalizeEnumValues($options);

                    if (!empty($enumValues)) {

                        $enumSql = '';

                        foreach ($enumValues as $value) {
                            $escaped = $this->mysqli->real_escape_string($value);
                            $enumSql .= "'$escaped', ";
                        }

                        $type .= "(".substr($enumSql, 0, -2).")";

                    } else {

                        # Fallback sicuro se enum è definito ma senza valori.
                        $type = "VARCHAR(".$this->DEFAULT_LENGHT_VARCHAR.")";

                    }

                } else {

                    $length = empty($options['length']) ? $defaultLenght : $options['length'];
                    $type .= ($length == null) ? '': "($length)";

                }

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

                        $isNullableColumn = !isset($options['null']) || $options['null'] == true;
                        $defaultOnDelete = $isNullableColumn ? 'SET NULL' : 'RESTRICT';

                        $foreignOnUpdate = $this->normalizeReferentialAction(
                            $options['foreign_on_update'] ?? 'CASCADE',
                            'CASCADE'
                        );

                        $foreignOnDelete = $this->normalizeReferentialAction(
                            $options['foreign_on_delete'] ?? $defaultOnDelete,
                            $defaultOnDelete
                        );

                        # Evita SQL non valido: SET NULL su colonna NOT NULL.
                        if (!$isNullableColumn && $foreignOnDelete === 'SET NULL') {
                            $foreignOnDelete = 'RESTRICT';
                        }

                        $foreign = ", ";

                        if ($action == 'add' || $action == 'modify') {
                            $foreign .= "ADD ";
                        }

                        $foreign .= "FOREIGN KEY (`$name`) REFERENCES `$foreignTable` (`$foreignKey`) ON UPDATE $foreignOnUpdate ON DELETE $foreignOnDelete ";
                        
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

            $tableOptions = $this->extractTableOptions($columns);
            $constraints = $this->extractConstraints($columns);

            # La presenza di una PK composta disabilita l'id automatico.
            if (!empty($constraints['primary'])) {
                $tableOptions['auto_id'] = false;
            }

            $beforeColumns = [];

            if ($tableOptions['auto_id']) {
                $beforeColumns = [
                    'id' => [
                        'type'=> 'INT',
                        'primary'=> true,
                        'null' => false,
                        'auto_increment' => true
                    ]
                ];
            }

            $afterColumns = [];

            if ($tableOptions['audit_columns']) {

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
                ];

                if ($tableOptions['auto_id']) {
                    $afterColumns['ind_id'] = [
                        'index'=> 'id'
                    ];
                }
            }

            $columns = array_merge($beforeColumns, $columns, $afterColumns);
            $columns = $this->appendConstraints($columns, $constraints);

            if ($SQL->TableExists($name)) {
                
                $query = "";
                $columnBefore = $tableOptions['auto_id'] ? "id" : null;

                # Elimino tutte le FOREIGN KEY
                    foreach (array_values(array_unique($SQL->ColumnForeign($name))) as $foreignKey) { 

                        if (!empty($foreignKey) && $foreignKey != "PRIMARY") {
                            $query .= "DROP FOREIGN KEY `$foreignKey`, "; 
                        }
                    
                    }

                # Elimino tutti gli INDEX
                    foreach (array_values(array_unique($SQL->TableIndex($name))) as $indexName) { 
                        
                        if (empty($indexName)) {
                            continue;
                        }
                        
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
                            $columnSql = $this->TableColumn( $columnName, $options, 'modify' );
                            if ($columnSql !== '') {
                                $query .= $columnSql.', ';
                            }

                        } else {

                            # Aggiungo la colonna
                            if (is_array($options) && $this->isColumnDefinition($options) && !isset($options['after']) && !empty($columnBefore)) {
                                $options['after'] = $columnBefore;
                            }
                            $columnSql = $this->TableColumn( $columnName, $options, 'add' );
                            if ($columnSql !== '') {
                                $query .= $columnSql.', ';
                            }

                        }

                        if (is_array($options) && $this->isColumnDefinition($options)) {
                            $columnBefore = $columnName;
                        }

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
                    $columnSql = $this->TableColumn( $columnName, $options );
                    if ($columnSql !== '') {
                        $query .= $columnSql.', ';
                    }

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
