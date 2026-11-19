<?php

    namespace Wonder\Sql;

    use Wonder\App\Credentials;
    use Wonder\App\Environment;
    use Wonder\App\EnvironmentException;
    use Wonder\Sql\{ Connection, Query };
    use Wonder\Sql\Utility\Error;

    use mysqli;

    class CreateTable {

        public mysqli $mysqli;
        public bool $debug = false;

        public string $ENGINE = "InnoDB";
        public string $CHARSET = "latin1";
        public string $COLLATION = "latin1_swedish_ci";
        public string $DEFAULT_TYPE = "VARCHAR";
        public int $DEFAULT_LENGHT_VARCHAR = 1000;
        public int $DEFAULT_LENGHT_BIGINT = 18;
        public int $DEFAULT_LENGHT_INT = 10;
        public $DEFAULT_LENGHT_DECIMAL = '10,2';

        public array $SQL_VAR = [ 'PRIMARY KEY', 'CURRENT_TIMESTAMP', 'AUTO_INCREMENT' ];

        /**
         * Operazioni distruttive (DROP COLUMN, DROP di indici, chiavi esterne e
         * chiave primaria non dichiarati) su una tabella che esiste già.
         * `null` = decide l'ambiente: vietate in `production` e `staging` (e se
         * `APP_ENV` non è leggibile), permesse solo in `local`. `true`/`false`
         * forzano il comportamento: lo userà una migrazione esplicita.
         */
        public ?bool $allowDestructive = null;

        /**
         * Operazioni distruttive saltate dall'ultima `Table()`, una per voce:
         * `['table' => , 'kind' => 'column'|'index'|'foreign_key'|'primary_key', 'name' => ]`.
         *
         * @var array<int, array{table: string, kind: string, name: string}>
         */
        public array $skipped = [];

        public function __construct( ?mysqli $connection = null ) {

            $this->mysqli = ($connection === null) ? Connection::Connect() : $connection;

            $databaseConfig = Credentials::database();
            $charset = trim((string) ($databaseConfig->charset ?? 'latin1'));
            $collation = trim((string) ($databaseConfig->collation ?? 'latin1_swedish_ci'));

            $this->CHARSET = $charset !== '' ? $charset : 'latin1';
            $this->COLLATION = $collation !== '' ? $collation : 'latin1_swedish_ci';

        }
        
        public function debug(bool $debug = true): void 
        {
            $this->debug = $debug;
        }

        /** Vero se `Table()` può emettere DROP su una tabella esistente. */
        public function destructiveAllowed(): bool
        {

            if ($this->allowDestructive !== null) {
                return $this->allowDestructive;
            }

            try {
                return Environment::current() === Environment::LOCAL;
            } catch (EnvironmentException) {
                # Ambiente sconosciuto: nel dubbio non si distrugge nulla.
                return false;
            }

        }

        /**
         * Cosa dichiara lo schema: nomi degli indici (minuscoli), se c'è una
         * chiave primaria e se ci sono chiavi esterne. Segue lo stesso ordine di
         * precedenza di `TableColumn()`, perché i DROP in produzione toccano solo
         * ciò che la stessa query ricrea.
         *
         * @return array{indexes: array<int, string>, primary: bool, foreign: bool, foreign_columns: array<int, string>}
         */
        private function declaredKeys(array $columns): array
        {

            $declared = [ 'indexes' => [], 'primary' => false, 'foreign' => false, 'foreign_columns' => [] ];

            foreach ($columns as $name => $options) {

                if (!is_array($options)) {
                    continue;
                }

                $originalName = (string) $name;
                $name = strtolower($originalName);

                if (isset($options['vector_index']) && is_array($options['vector_index'])) {

                    if (count($this->normalizeColumnList($options['vector_index']['column'] ?? null)) === 1) {
                        $declared['indexes'][] = $name;
                    }

                } elseif (isset($options['index']) && !empty($options['index'])) {

                    if (!empty($this->normalizeColumnList($options['index'], $name))) {
                        $declared['indexes'][] = $name;
                    }

                } elseif (isset($options['unique'])) {

                    if (!empty($this->normalizeColumnList($options['unique'], $name))) {
                        $declared['indexes'][] = $name;
                    }

                } elseif (isset($options['primary']) && is_array($options['primary'])) {

                    if (!empty($this->normalizeColumnList($options['primary']))) {
                        $declared['primary'] = true;
                    }

                } else {

                    if (isset($options['primary']) && $options['primary'] == true) {
                        $declared['primary'] = true;
                    }

                    if (!empty($options['foreign_table'])) {
                        $declared['foreign'] = true;
                        $declared['foreign_columns'][] = $originalName;
                    }

                }

            }

            return $declared;

        }

        private function skip(string $table, string $kind, string $name): void
        {

            $this->skipped[] = [ 'table' => $table, 'kind' => $kind, 'name' => $name ];

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

            if (isset($options['vector_index'])) {
                return false;
            }

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
         * @return array{auto_id: bool, audit_columns: bool, audit_auto_columns: bool}
         */
        private function extractTableOptions(array &$columns): array
        {

            $tableOptions = [
                'auto_id' => true,
                'audit_columns' => true,
                'audit_auto_columns' => true,
            ];

            if (isset($columns['__table']) && is_array($columns['__table'])) {

                $config = $columns['__table'];

                if (isset($config['auto_id'])) {
                    $tableOptions['auto_id'] = (bool) $config['auto_id'];
                }

                if (isset($config['audit_columns'])) {
                    $tableOptions['audit_columns'] = (bool) $config['audit_columns'];
                }

                if (isset($config['audit_auto_columns'])) {
                    $tableOptions['audit_auto_columns'] = (bool) $config['audit_auto_columns'];
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

        private function usesVector(array $columns): bool
        {

            foreach ($columns as $options) {

                if (!is_array($options)) {
                    continue;
                }

                if (isset($options['vector_index']) || strtoupper((string) ($options['type'] ?? '')) === 'VECTOR') {
                    return true;
                }

            }

            return false;

        }

        private function TableColumn( $name, $options, $action = false ) {

            if (isset($options['vector_index']) && is_array($options['vector_index'])) {

                # Se bisogna creare un indice vettoriale

                $vectorIndex = $options['vector_index'];
                $vectorColumns = $this->normalizeColumnList($vectorIndex['column'] ?? null);

                if (count($vectorColumns) !== 1) {
                    return '';
                }

                $indexSql = "VECTOR INDEX `$name` (".$this->columnListToSql($vectorColumns).")";

                if (!empty($vectorIndex['m'])) {
                    $indexSql .= " M=".(int) $vectorIndex['m'];
                }

                $indexSql .= " DISTANCE=".Vector::metric((string) ($vectorIndex['distance'] ?? 'cosine'));

                $return = $this->buildConstraintSql($indexSql, $action);

            } elseif (isset($options['index']) && !empty($options['index'])) {

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
                        $isBooleanType = preg_match('/^(BOOLEAN|BOOL)\b/i', $type) === 1;

                        $default = "DEFAULT ";

                        if (in_array($defaultValue, $this->SQL_VAR)) {
                            $default .= $defaultValue;
                        } elseif (
                            $isBooleanType &&
                            (
                                is_bool($defaultValue) ||
                                in_array(strtolower((string) $defaultValue), ['true', 'false', '1', '0'], true)
                            )
                        ) {
                            $default .= filter_var($defaultValue, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
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
            $this->skipped = [];

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

            if ($tableOptions['audit_columns'] && $tableOptions['audit_auto_columns']) {

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

            if ($this->usesVector($columns) && !Vector::supported($this->mysqli)) {
                throw new \RuntimeException(
                    "Tabella `$name`: le colonne VECTOR richiedono MariaDB ".Vector::MIN_SERVER." o successivo (server: ".$this->mysqli->server_info.")."
                );
            }

            if ($SQL->TableExists($name)) {
                
                $query = "";
                $columnBefore = $tableOptions['auto_id'] ? "id" : null;

                $destructive = $this->destructiveAllowed();
                $declared = $this->declaredKeys($columns);

                # Elimino tutte le FOREIGN KEY. In produzione solo quelle delle
                # colonne per cui lo schema dichiara una FK (la stessa query le
                # ricrea; i nomi li assegna MySQL): le altre si saltano e si segnalano.
                    $recreatedForeign = [];

                    if (!$destructive) {
                        foreach ($declared['foreign_columns'] as $foreignColumn) {
                            foreach ($SQL->ColumnForeign($name, $foreignColumn) as $foreignName) {
                                $recreatedForeign[(string) $foreignName] = true;
                            }
                        }
                    }

                    foreach (array_values(array_unique($SQL->ColumnForeign($name))) as $foreignKey) { 

                        if (!empty($foreignKey) && $foreignKey != "PRIMARY") {

                            if ($destructive || isset($recreatedForeign[(string) $foreignKey])) {
                                $query .= "DROP FOREIGN KEY `$foreignKey`, "; 
                            } else {
                                $this->skip($name, 'foreign_key', (string) $foreignKey);
                            }

                        }
                    
                    }

                # Elimino tutti gli INDEX
                    foreach (array_values(array_unique($SQL->TableIndex($name))) as $indexName) { 
                        
                        if (empty($indexName)) {
                            continue;
                        }

                        # In produzione si tolgono solo gli indici che la stessa
                        # query ricrea: gli altri (aggiunti a mano) restano.
                        if (!$destructive) {

                            $isPrimary = ($indexName == 'PRIMARY');

                            if ($isPrimary ? !$declared['primary'] : !in_array(strtolower((string) $indexName), $declared['indexes'], true)) {
                                $this->skip($name, $isPrimary ? 'primary_key' : 'index', (string) $indexName);
                                continue;
                            }

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

                            if ($destructive) {
                                $query .= "DROP COLUMN $column, ";
                            } else {
                                $this->skip($name, 'column', (string) $column);
                            }

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
                $charsetQuery = "ALTER TABLE `{$name}` DEFAULT CHARACTER SET = {$this->CHARSET}";
                if ($this->COLLATION !== '') {
                    $charsetQuery .= " COLLATE = {$this->COLLATION}";
                }
                $this->mysqli->query($charsetQuery);

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
                if ($this->COLLATION !== '') {
                    $query .= "COLLATE = ".$this->COLLATION." ";
                }
                $query .= ";";

            }

            if (!empty($query)) {

                if ($this->debug) {

                    echo str_replace(", ", ",<br>", $query);

                } else {

                    # La migrazione può aggiungere FOREIGN KEY su tabelle che
                    # contengono già dati (ALTER) o creare tabelle il cui
                    # riferimento non è ancora stato creato (ordine di CREATE):
                    # disabilita i controlli FK per il singolo DDL così ALTER/CREATE
                    # non falliscono per dati pre-esistenti o dipendenze non ancora
                    # presenti. L'integrità resta garantita dalle scritture runtime.
                    $this->mysqli->query('SET FOREIGN_KEY_CHECKS = 0');
                    $ddlOk = $this->mysqli->query( $query );
                    // Struttura cambiata: le righe memoizzate della tabella non valgono più.
                    \Wonder\App\Support\RequestMemo::forget($name);

                    if ($ddlOk) {

                        $this->mysqli->query('SET FOREIGN_KEY_CHECKS = 1');

                        $RETURN =  (object) array();
                        $RETURN->table = $name;
                        $RETURN->query = $query;
                        $RETURN->skipped = $this->skipped;
                        $this->reportSkipped();

                        return $RETURN;

                    } else {

                        # Riporta l'errore reale del DDL PRIMA di riabilitare i
                        # controlli FK: un'ulteriore query (il SET) azzererebbe lo
                        # stato di errore di mysqli letto da Error().
                        new Error( 'Table', $name, $query, $this->mysqli );
                        $this->mysqli->query('SET FOREIGN_KEY_CHECKS = 1');

                    }

                }

            }

            # Nessun ALTER da eseguire, ma qualcosa è stato saltato: va comunque segnalato.
            if (empty($query) && !empty($this->skipped)) {

                $this->reportSkipped();

                $RETURN = (object) array();
                $RETURN->table = $name;
                $RETURN->query = '';
                $RETURN->skipped = $this->skipped;

                return $RETURN;

            }

        }

        /** Una riga di log per ogni operazione distruttiva saltata (non contiene dati, solo nomi). */
        private function reportSkipped(): void
        {

            foreach ($this->skipped as $item) {
                @error_log('[wonder-image] DROP saltato (ambiente non locale): '.$item['kind'].' `'.$item['name'].'` della tabella `'.$item['table'].'` (serve una migrazione esplicita).');
            }

        }

    }
