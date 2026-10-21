<?php

    namespace Wonder\Sql;

    use Wonder\Sql\Connection;
    use Wonder\Sql\Utility\Error;

    use mysqli;
    use RuntimeException;

    class Query {

        public mysqli $mysqli;
        private bool $informationSchema = false;
        private ?\Closure $escaper = null;

        public function __construct( ?mysqli $connection = null, bool $informationSchema = false ) 
        { 
            
            $this->mysqli = ($connection === null) ? Connection::Connect('main') : $connection; 
            $this->informationSchema = $informationSchema;
        
        }

        public static function Conditions( string | array $conditions, bool $where = true ): string
        {

            if (is_string($conditions)) {
                return self::stringConditions($conditions, $where);
            }

            $query = new self();
            return $query->buildConditions($conditions, $where);

        }

        private function buildConditions( string | array $conditions, bool $where = true ): string
        {
            if (is_array($conditions) && $conditions === []) {
                return '';
            }

            if (is_string($conditions)) {
                return self::stringConditions($conditions, $where);
            }

            $filter = $where ? "WHERE " : "";
        
            if (is_array($conditions)) {

                foreach ($conditions as $label => $value) {

                    if (is_array(json_decode($label, true))) { 

                        # Se come colonna c'è un array in JSON converire e creare la query tramite CONCAT_WS

                        $label = json_decode($label, true);
                        $labelConcat = "CONCAT_WS(' ', ";

                        foreach ($label as $key => $l) {
                            $labelConcat .= "$l, ";
                        }

                        $labelConcat = substr($labelConcat, 0, -2).")";

                        $label = $labelConcat; 

                    } else {

                        $label = "$label"; 

                    }

                    if (is_array($value)) {

                        $filter .= "$label IN (";

                        foreach ($value as $v) {
                            $filter .= $this->valueToSql($v, false).", ";
                        }
                        $filter = substr($filter, 0, -2); 

                        $filter .= ") AND ";

                    } else {

                        if ($value === null) {
                            $filter .= "$label IS NULL AND ";
                        } else {
                            $filter .= "$label = ".$this->valueToSql($value, false)." AND ";
                        }

                    }
                }

                $filter = substr($filter, 0, -4);

            }

            return " $filter";

        }

        /**
         * A string condition may start with its own WHERE: only that leading
         * keyword is dropped, never the ones of a subquery or inside a word.
         */
        private static function stringConditions( string $conditions, bool $where ): string
        {

            if (trim($conditions) === '') {
                return '';
            }

            $body = preg_replace('/^\s*WHERE\b\s*/i', '', $conditions, 1);

            return ' '.($where ? 'WHERE ' : '').$body;

        }

        private function escapeString(string $value): string
        {

            if ($value === '') {
                return '';
            }

            if ($this->escaper !== null) {
                return (string) ($this->escaper)($value);
            }

            return $this->mysqli->real_escape_string($value);

        }

        /**
         * Istanza senza connessione con un escape dato (per i test di bind()).
         */
        public static function withEscaper( callable $escaper ): self
        {

            $query = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
            $query->escaper = \Closure::fromCallable($escaper);

            return $query;

        }

        /**
         * Sostituisce i segnaposto `?` (posizionali) e `:nome` con i valori
         * escapati: null → NULL, int/float → numero, bool → 1/0, stringa →
         * letterale quotato. I segnaposto dentro stringhe, identificatori e
         * commenti restano intatti. Un segnaposto senza valore o un valore
         * non usato lanciano RuntimeException.
         *
         * @param array<int|string,mixed> $params chiavi intere per `?`, stringhe (con o senza `:`) per `:nome`
         */
        public function bind( string $sql, array $params ): string
        {

            $positional = [];
            $named = [];

            foreach ($params as $key => $value) {
                if (is_int($key)) {
                    $positional[] = $value;
                } else {
                    $named[ltrim($key, ':')] = $value;
                }
            }

            $out = '';
            $length = strlen($sql);
            $next = 0;
            $usedNamed = [];

            for ($i = 0; $i < $length; $i++) {

                $char = $sql[$i];

                if ($char === "'" || $char === '"' || $char === '`') {
                    $end = self::quotedEnd($sql, $i, $char);
                    $out .= substr($sql, $i, $end - $i + 1);
                    $i = $end;
                    continue;
                }

                if ($char === '#' || ($char === '-' && substr($sql, $i, 2) === '--' && ($i + 2 >= $length || ctype_space($sql[$i + 2]) || ctype_cntrl($sql[$i + 2])))) {
                    $end = strpos($sql, "\n", $i);
                    $end = $end === false ? $length - 1 : $end;
                    $out .= substr($sql, $i, $end - $i + 1);
                    $i = $end;
                    continue;
                }

                if ($char === '/' && substr($sql, $i, 2) === '/*') {
                    $end = strpos($sql, '*/', $i + 2);
                    $end = $end === false ? $length - 1 : $end + 1;
                    $out .= substr($sql, $i, $end - $i + 1);
                    $i = $end;
                    continue;
                }

                if ($char === '?') {
                    if (!array_key_exists($next, $positional)) {
                        throw new RuntimeException('Query::bind: segnaposto ? numero '.($next + 1).' senza valore.');
                    }
                    $out .= $this->bindValue($positional[$next++]);
                    continue;
                }

                if ($char === ':' && ($i === 0 || $sql[$i - 1] !== ':')
                    && preg_match('/\G:([A-Za-z_][A-Za-z0-9_]*)/', $sql, $match, 0, $i)) {
                    $name = $match[1];
                    if (!array_key_exists($name, $named)) {
                        throw new RuntimeException('Query::bind: segnaposto :'.$name.' senza valore.');
                    }
                    $out .= $this->bindValue($named[$name]);
                    $usedNamed[$name] = true;
                    $i += strlen($match[0]) - 1;
                    continue;
                }

                $out .= $char;

            }

            if ($next < count($positional) || count($usedNamed) < count($named)) {
                throw new RuntimeException('Query::bind: valori senza segnaposto.');
            }

            return $out;

        }

        /** Come quotedEnd(), ma null se la stringa aperta in $start non si chiude. */
        private static function closingQuote( string $sql, int $start, string $quote ): ?int
        {

            $length = strlen($sql);

            for ($i = $start + 1; $i < $length; $i++) {
                if ($sql[$i] === '\\' && $quote !== '`') {
                    $i++;
                    continue;
                }
                if ($sql[$i] === $quote) {
                    if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                        $i++;
                        continue;
                    }
                    return $i;
                }
            }

            return null;

        }

        /** Indice del carattere che chiude la stringa/identificatore aperto in $start. */
        private static function quotedEnd( string $sql, int $start, string $quote ): int
        {

            $length = strlen($sql);

            for ($i = $start + 1; $i < $length; $i++) {
                if ($sql[$i] === '\\' && $quote !== '`') {
                    $i++;
                    continue;
                }
                if ($sql[$i] === $quote) {
                    if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                        $i++;
                        continue;
                    }
                    return $i;
                }
            }

            return $length - 1;

        }

        private function bindValue( mixed $value ): string
        {

            if ($value === null) {
                return 'NULL';
            }

            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            if (is_int($value)) {
                return (string) $value;
            }

            if (is_float($value)) {
                if (!is_finite($value)) {
                    throw new RuntimeException('Query::bind: numero non finito.');
                }
                return var_export($value, true);
            }

            if (is_string($value)) {
                return "'".$this->escapeString($this->protectLatin1String($value))."'";
            }

            throw new RuntimeException('Query::bind: tipo '.get_debug_type($value).' non supportato.');

        }

        /**
         * Quote a SQL identifier (table / column name), doubling any backtick so
         * a caller can never break out of the quoted context. Use this for every
         * identifier interpolated into a query. Values (not identifiers) must go
         * through valueToSql()/escapeString() instead.
         */
        public static function escapeIdentifier(string $identifier): string
        {
            return '`' . str_replace('`', '``', $identifier) . '`';
        }

        /**
         * ` ORDER BY ...` di Select e SelectForUpdate. `$order` resta SQL
         * scritto dal codice (colonne, espressioni, sottoquery) e va sempre
         * scelto da una whitelist decisa dal codice, mai preso dalla
         * richiesta: le sottoquery restano ammesse, quindi un order costruito
         * dall'input permetterebbe injection booleane o a tempo. Esempio:
         * `CatalogFilter::applyOrder()` di ecommerce mappa il parametro con un
         * `match` su ordinamenti fissi. Fuori dalle stringhe non può avere
         * `;`, `--`, `#` o `/*`; dentro le stringhe non può avere `\`, che
         * con o senza NO_BACKSLASH_ESCAPES verrebbe letta in modo diverso. La
         * direzione vale solo ASC o DESC, maiuscola o minuscola; null o vuota
         * = nessuna; una direzione senza order non è ammessa. Il resto lancia
         * RuntimeException prima della query.
         */
        public static function orderClause( ?string $order, ?string $orderDirection ): string
        {

            $clause = '';

            if ($order !== null && trim($order) !== '') {

                $length = strlen($order);

                for ($i = 0; $i < $length; $i++) {

                    $char = $order[$i];

                    if ($char === "'" || $char === '"' || $char === '`') {
                        $end = self::closingQuote($order, $i, $char)
                            ?? throw new RuntimeException('Query: ORDER BY con una stringa non chiusa.');
                        if ($char !== '`' && str_contains(substr($order, $i, $end - $i), '\\')) {
                            throw new RuntimeException('Query: ORDER BY con \\ dentro una stringa non ammesso.');
                        }
                        $i = $end;
                        continue;
                    }

                    if ($char === ';' || $char === '#' || substr($order, $i, 2) === '--' || substr($order, $i, 2) === '/*') {
                        throw new RuntimeException('Query: ORDER BY con ; o commenti non ammesso.');
                    }

                }

                $clause .= " ORDER BY $order";

            }

            if ($orderDirection !== null && trim($orderDirection) !== '') {

                if ($clause === '') {
                    throw new RuntimeException('Query: direzione ORDER BY senza colonne da ordinare.');
                }

                $direction = strtoupper(trim($orderDirection));

                if ($direction !== 'ASC' && $direction !== 'DESC') {
                    throw new RuntimeException('Query: direzione ORDER BY non valida, ammesse ASC e DESC.');
                }

                $clause .= " $direction";

            }

            return $clause;

        }

        /**
         * Whitelist a LIMIT clause. Only a plain count ("10") or the
         * "offset, count" form ("0, 25") survive; anything else (injection
         * attempts, expressions) collapses to '' so it is dropped from the
         * query. Callers still pass trusted integers in normal use.
         *
         * @param mixed $limit
         */
        public static function sanitizeLimit($limit): string
        {
            if ($limit === null) {
                return '';
            }

            $limit = trim((string) $limit);

            if ($limit === '') {
                return '';
            }

            return preg_match('/^\d+(\s*,\s*\d+)?$/', $limit) === 1 ? $limit : '';
        }

        private function looksLikeJsonContainer(string $value): bool
        {

            $trimmed = trim($value);

            if ($trimmed === '') {
                return false;
            }

            $firstChar = $trimmed[0];

            if ($firstChar !== '{' && $firstChar !== '[') {
                return false;
            }

            json_decode($trimmed, true);

            return json_last_error() === JSON_ERROR_NONE;

        }

        private function unicodeCodePoint(string $char): ?int
        {

            if ($char === '') {
                return null;
            }

            if (function_exists('mb_ord')) {
                $codePoint = mb_ord($char, 'UTF-8');
                if (is_int($codePoint) && $codePoint >= 0) {
                    return $codePoint;
                }
            }

            $ucs4 = @iconv('UTF-8', 'UCS-4BE', $char);
            if (!is_string($ucs4) || strlen($ucs4) !== 4) {
                return null;
            }

            $decoded = unpack('Ncode', $ucs4);
            if (!is_array($decoded) || !isset($decoded['code'])) {
                return null;
            }

            return (int) $decoded['code'];

        }

        private function encodeNonLatin1Characters(string $value): string
        {

            if ($value === '') {
                return '';
            }

            if (preg_match('/[^\x{0000}-\x{00FF}]/u', $value) !== 1) {
                return $value;
            }

            $encoded = preg_replace_callback(
                '/[^\x{0000}-\x{00FF}]/u',
                function (array $matches): string {
                    $codePoint = $this->unicodeCodePoint((string) ($matches[0] ?? ''));

                    if ($codePoint === null) {
                        return (string) ($matches[0] ?? '');
                    }

                    return '&#'.$codePoint.';';
                },
                $value
            );

            return is_string($encoded) ? $encoded : $value;

        }

        private function protectLatin1String(string $value): string
        {

            if ($value === '') {
                return '';
            }

            if ($this->looksLikeJsonContainer($value)) {

                $decoded = json_decode($value, true, 512, JSON_BIGINT_AS_STRING);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $reEncoded = json_encode($decoded, JSON_UNESCAPED_SLASHES);
                    if (is_string($reEncoded)) {
                        return $reEncoded;
                    }
                }

            }

            return $this->encodeNonLatin1Characters($value);

        }

        private function valueToSql(mixed $value, bool $emptyStringAsNull = true): string
        {

            if ($value === null || ($emptyStringAsNull && $value === '')) {
                return "NULL";
            }

            if (is_int($value) || is_float($value)) {
                return "'".$value."'";
            }

            if (is_bool($value)) {
                return $value ? "'1'" : "'0'";
            }

            if (is_array($value) || is_object($value)) {
                // In latin1 manteniamo JSON in ASCII con escape \uXXXX.
                $encoded = json_encode($value, JSON_UNESCAPED_SLASHES);
                if (!is_string($encoded)) {
                    $encoded = '';
                }
                return "'".$this->escapeString($encoded)."'";
            }

            $stringValue = $this->protectLatin1String((string) $value);

            return "'".$this->escapeString($stringValue)."'";

        }

        // Genera la porzione SQL per INSERT/UPDATE
        public function Values( array $values, bool $correlated = false ): string
        {

            if ($correlated) {
                
                $query = "";

                foreach ($values as $l => $v) {
                    $query .= self::escapeIdentifier((string) $l)." = ".$this->valueToSql($v).", ";
                }

                return substr($query, 0, -2);

            } else {

                $label = "";
                $value = "";

                foreach ($values as $l => $v) {
                    $label .= self::escapeIdentifier((string) $l).", ";
                    $value .= $this->valueToSql($v).", ";
                }

                $label = substr($label, 0, -2);
                $value = substr($value, 0, -2);

                return "($label) VALUES ($value)";

            }

        }

        public static function JoinTable( array $tables ): string
        {
    
            $tableJoined = "";

            $mainTable = "";
            $mainKey = "";
            
            $i = 0;
            
            foreach ($tables as $table => $keys) {

                if ($i == 0) {
                    
                    $tableJoined .= $table;
                    $mainTable = $table;

                    if (is_string($keys)) {
                        $mainKey = $keys;
                    }

                } else {

                    if (is_array($keys)) {
                        $tableJoined .= " JOIN $table ON $mainTable.{$keys[0]} = $table.{$keys[1]}";
                    } else {
                        $tableJoined .= " JOIN $table ON $mainTable.$mainKey = $table.$keys";
                    }
                }

                $i++;
                
            }
    
            return $tableJoined;

        }

        public function Insert( string $table, string | array $values ): object
        {

            $query = "INSERT INTO ".self::escapeIdentifier($table)." ";
            $query .= $this->Values( $values );

            $RETURN = (object) [];
            $RETURN->success = true;
            $RETURN->table = $table;
            $RETURN->query = $query;

            if (!$this->mysqli->query( $query )) {
                
                $RETURN->success = false;

                new Error( 'Insert', $table, $query, $this->mysqli );

            } else {

                $RETURN->insert_id = $this->mysqli->insert_id;

            }
            
            return $RETURN;

        }

        public function Update( string $table, array $values, string $column, string | int $value ): object 
        {

            $query = "UPDATE ".self::escapeIdentifier($table)." SET ";
            $query .= $this->Values( $values, true );
            $query .= " WHERE ".self::escapeIdentifier($column)." = ".$this->valueToSql($value, false);

            $RETURN = (object) [];
            $RETURN->success = true;
            $RETURN->table = $table;
            $RETURN->query = $query;

            if (!$this->mysqli->query( $query )) { 

                $RETURN->success = false;
                
                new Error( 'Update', $table, $query, $this->mysqli ); 
            
            }

            return $RETURN;


        }

        public function Select( string | array $table, string | array | null $condition = null, string | int | null $limit = null, ?string $order = null, ?string $orderDirection = null, string | array $attributes = '*' ) 
        {

            return $this->runSelect($table, $condition, $limit, $order, $orderDirection, $attributes, false);

        }

        /**
         * Come `Select()` con `FOR UPDATE`: blocca le righe lette fino alla fine
         * della transazione. Consentito solo dentro `Transaction::run()`.
         */
        public function SelectForUpdate( string | array $table, string | array | null $condition = null, string | int | null $limit = null, ?string $order = null, ?string $orderDirection = null, string | array $attributes = '*' ) 
        {

            if (!Transaction::activeForMysqli($this->mysqli)) {
                throw new RuntimeException('SelectForUpdate richiede una transazione attiva (Transaction::run).');
            }

            return $this->runSelect($table, $condition, $limit, $order, $orderDirection, $attributes, true);

        }

        private function runSelect( string | array $table, string | array | null $condition, string | int | null $limit, ?string $order, ?string $orderDirection, string | array $attributes, bool $forUpdate ) 
        {

            $query = "SELECT ";
            $query .= is_array($attributes) ? implode(",", $attributes) : $attributes;
            $query .= " FROM ";
            if (is_array($table)) {
                $query .= self::JoinTable( $table );
            } else {
                $query .= $this->informationSchema ? "INFORMATION_SCHEMA.$table" : self::escapeIdentifier($table);
            }
            $query .= ($condition == null) ? "" : $this->buildConditions( $condition, true );
            $query .= self::orderClause($order, $orderDirection);
            $safeLimit = self::sanitizeLimit($limit);
            $query .= ($safeLimit === '') ? "" : " LIMIT $safeLimit";
            $query .= $forUpdate ? " FOR UPDATE" : "";

            $RETURN = (object) [];

            $RETURN->success = true;
            $RETURN->row = [];
            
            if (!$RESULT = $this->mysqli->query( $query )) {
                
                $RETURN->success = false;

                new Error( 'Select', $table, $query, $this->mysqli );

            } else {

                $RETURN->exists = ($RESULT->num_rows > 0) ? true : false;
                $RETURN->Nrow = $RESULT->num_rows;

                if ($RESULT->num_rows == 1) {

                    if ($limit == 1) {
                        $RETURN->row = $RESULT->fetch_assoc();
                        $RETURN->id = $RETURN->row['id'] ?? "";
                    } else {
                        $RETURN->row = $RESULT->fetch_all( MYSQLI_ASSOC );
                        $RETURN->id = $RETURN->row[0]['id'] ?? "";
                    }

                } else if ($RESULT->num_rows >= 2) {

                    $RETURN->row = $RESULT->fetch_all( MYSQLI_ASSOC );

                }

            }
            
            return $RETURN;
        
        }

        public function Delete( string $table, string | array | null $condition = null ): string
        {

            $query = "DELETE FROM ";
            $query .= self::escapeIdentifier($table);
            $query .= ($condition == null) ? "" : $this->buildConditions( $condition, true );

            if (!$RESULT = $this->mysqli->query( $query )) {
                
                new Error( 'Delete', $table, $query, $this->mysqli );

            }

            return $RESULT;

        }

        public function Truncate( string $table ): string
        {

            $query = "TRUNCATE TABLE ";
            $query .= self::escapeIdentifier($table);

            if (!$RESULT = $this->mysqli->query( $query )) {
                
                new Error( 'Truncate', $table, $query, $this->mysqli );

            }

            return $RESULT;

        }

        /**
         * 
         * EXTRA FUNCTIONS
         * 
         */
        public function GetDatabase(): string
        {

            $query = "SELECT DATABASE() AS db";

            if (!$RESULT = $this->mysqli->query( $query )) {
                
                new Error( 'GetDatabase', '', $query, $this->mysqli );
                return '';

            } else {

                $row = $RESULT->fetch_assoc();

                return $row['db'];

            }

        }

        public function Sum( string | array  $table, string | array $query, string $column = '*' ): float
        {

            $ATTRIBUTES = "SUM($column)";
            $n = $this->Select( $table, $query, null, null, null, $ATTRIBUTES )->row[0][$ATTRIBUTES];
    
            return empty($n) ? 0 : $n;
    
        }

        public function Count( string | array  $table, string | array | null $query = null, string $column = '*', bool $distinct = false ): int
        {

            $DISTINCT = $distinct ? "DISTINCT " : "";
            $ATTRIBUTES = "COUNT($DISTINCT$column)";
            $n = $this->Select($table, $query, null, null, null, $ATTRIBUTES)->row[0][$ATTRIBUTES];
    
            return empty($n) ? 0 : $n;

        }

        
        /**
         * 
         * Controlla se il database esiste
         * @param mixed $name
         * @return bool
         * 
         */
        public function DatabaseExists( string $name ) : bool 
        {

            $query = "SHOW DATABASES LIKE ";
            $query .= "'".$this->escapeString($name)."'";

            if (!$RESULT = $this->mysqli->query( $query )) {
                
                new Error( 'DatabaseExists', '', $query, $this->mysqli );
                return false;

            } else {

                return ($RESULT->num_rows === 0) ? false : true;

            }

        }

        /**
         * 
         * Controlla che la tabella esiste
         * @param mixed $name
         * @return bool
         * 
         */
        public function TableExists( string $name ) : bool
        {

            $query = "SHOW TABLES LIKE ";
            $query .= "'".$this->escapeString($name)."'";

            if (!$RESULT = $this->mysqli->query( $query )) {
                
                new Error( 'TableExists', '', $query, $this->mysqli );
                return false;

            } else {

                return ($RESULT->num_rows === 0) ? false : true;

            }

        }

        /**
         * 
         * Controlla che la colonna di una determinata tabella esiste
         * @param mixed $table
         * @param mixed $column
         * @return bool
         * 
         */
        public function ColumnExists( string $table, string $column ) : bool
        {

            $query = "SHOW COLUMNS FROM ";
            $query .= self::escapeIdentifier($table)." ";
            $query .= "LIKE ";
            $query .= "'".$this->escapeString($column)."'";

            if (!$RESULT = $this->mysqli->query( $query )) {
                
                new Error( 'ColumnExists', $table, $query, $this->mysqli );
                return false;

            } else {

                return ($RESULT->num_rows === 0) ? false : true;

            }

        }

        /**
         * 
         * Connessione al database information_schema (senza aprire una nuova connessione)
         * 
         * @return Query
         * 
         */
        function ISConnection() : Query
        { 

            return new Query($this->mysqli, true); 

        }

        /**
         * 
         * Recupera le informazioni della colonna
         * 
         * @param mixed $table 
         * @param mixed $column
         * @return array
         * 
         */
        function ColumnInfo( string $table, string $column ) : array
        {

            return $this->ISConnection()->Select( 'columns', [ 'table_schema' => $this->GetDatabase(), 'table_name' => $table, 'column_name' => $column ], 1 )->row;

        }

        function ColumnForeign( string $table, ?string $column = null ) : array
        {
            $database = $this->escapeString($this->GetDatabase());
            $tableEscaped = $this->escapeString($table);

            $query = "SELECT DISTINCT `constraint_name` AS cName ";
            $query .= "FROM INFORMATION_SCHEMA.`key_column_usage` ";
            $query .= "WHERE `table_schema` = '{$database}' ";
            $query .= "AND `table_name` = '{$tableEscaped}' ";
            $query .= "AND `referenced_table_name` IS NOT NULL ";

            if ($column != null) {
                $columnEscaped = $this->escapeString($column);
                $query .= "AND `column_name` = '{$columnEscaped}' ";
            }

            $result = $this->mysqli->query($query);

            if ($result === false) {
                new Error( 'ColumnForeign', $table, $query, $this->mysqli );
                return [];
            }

            if ($result->num_rows === 0) {
                return [];
            }

            return array_column($result->fetch_all(MYSQLI_ASSOC), 'cName');

        }

        function TableIndex( string $table, ?string $column = null  )
        {

            $condition = [
                'table_schema' => $this->GetDatabase(),
                'table_name' => $table
            ];

            if ($column != null) {
                $condition['column_name'] = $column;
            }

            return array_column(
                $this->ISConnection()->Select( 'statistics', $condition, null, null, null, 'DISTINCT index_name as iName' )->row, 
                'iName'
            );

        }

        /**
         * 
         * Recupera le colonne della tabella
         * 
         * @param mixed $table
         * @return array
         * 
         */
        function TableColumn( string $table ) : array
        {

            return array_column(
                $this->ISConnection()->Select( 'columns', [ 'table_schema' => $this->GetDatabase(), 'table_name' => $table ], null, null, null, 'column_name as cName' )->row, 
                'cName'
            );

        }

    }
