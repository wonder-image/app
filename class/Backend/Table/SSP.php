<?php

    namespace Wonder\Backend\Table;

    use mysqli;
    use mysqli_result;
    use RuntimeException;
    use Throwable;
    use Wonder\App\Credentials;
    use Wonder\App\Debug;
    use Wonder\App\Logger;
    use Wonder\Sql\Query;

    class SSP {

        /** Messaggio mostrato al client quando una query della lista fallisce. */
        public const FATAL_MESSAGE = 'Errore nel caricamento della tabella.';
        
        static function data_output ($columns, $data)
        {
            $out = array();

            for ( $i=0, $ien=count($data) ; $i<$ien ; $i++ ) {

                $row = $data[$i];

                $arrayRow = [];

                for ( $j=0, $jen=count($columns) ; $j<$jen ; $j++ ) {

                    $column = $columns[$j];

                    $columnName = $column['db']; # Nome della colonna
                    $columnN = $column['dt']; # Numero della colonna
                    $columnFormat = $column['format']; # Formato della colonna

                    // C'è qualche funzione per formattare il dato estratto?
                    if (isset($column['formatter'])) {

                        $columnFormatter = $column['formatter']; # Funzione che modifica la colonna

                        if(empty($columnName)){

                            $arrayRow[$columnN] = $columnFormatter($row);

                        } elseif (empty($columnFormat)) {

                            $arrayRow[$columnN] = $columnFormatter($row, $columnName);

                        } else {

                            $arrayRow[$columnN] = $columnFormatter($row, $columnName, $columnFormat);
    
                        }
                    } else {

                        if(!empty($columnName)){
                            $arrayRow[$columnN] = $row[$columnName];
                        } else{
                            $arrayRow[$columnN] = "";
                        }

                    }

                }

                $out[] = $arrayRow;

            }

            return $out;

        }


        /**
         * Database connection
         *
         * Connessione mysqli: quella passata, o una nuova da un array di parametri
         *
         *  @param  mixed $conn SQL connection details. The array should have
         *    the following properties
         *     * host - host name
         *     * db   - database name
         *     * user - user name
         *     * pass - user password
         *     * Optional: `'charset' => 'utf8'` - you might need this depending on your PHP / MySQL config
         *  @return mysqli connessione mysqli
         */
        static function db ( $conn )
        {
            if ( is_array( $conn ) ) {
                return self::sql_connect( $conn );
            }

            if ( !$conn instanceof mysqli ) {
                self::fatal( 'SSP: serve una connessione mysqli o un array di parametri.' );
            }

            return $conn;
        }


        /**
         * Paging
         *
         * Construct the LIMIT clause for server-side processing SQL query
         *
         *  @param  array $request Data sent to server by DataTables
         *  @param  array $columns Column information array
         *  @return string SQL limit clause
         */
        static function limit ( $request, $columns )
        {
            $limit = '';

            if ( isset($request['start']) && $request['length'] != -1 ) {
                $limit = "LIMIT ".intval($request['start']).", ".intval($request['length']);
            }

            return $limit;
        }


        /**
         * Ordering
         *
         * Construct the ORDER BY clause for server-side processing SQL query
         *
         *  @param  array $request Data sent to server by DataTables
         *  @param  array $columns Column information array
         *  @return string SQL order by clause
         */
        static function order ( $column, $direction ) {

            $order = "";

            if (!empty($column)) {

                // `$column` / `$direction` arrive from the client
                // (order[0][name] / order[0][dir]). Escape the identifier and
                // whitelist the direction so neither can break out of the
                // ORDER BY clause.
                $safeColumn    = self::escapeIdentifier((string) $column);
                $safeDirection = self::normalizeDirection((string) $direction);

                $order = "ORDER BY $safeColumn $safeDirection";

            }

            return $order;

        }

        /**
         * Quote a SQL identifier (table/column name), doubling any backtick so
         * client-supplied identifiers can never break out of the quoted
         * context. Never pass user input into SQL as a bare identifier.
         */
        static function escapeIdentifier ( string $identifier )
        {
            return Query::escapeIdentifier( $identifier );
        }

        /**
         * Build a CONCAT_WS(' ', ...) list from a set of identifiers, escaping
         * every identifier. Used to search across several columns at once.
         *
         * @param array $columns List of column identifiers
         */
        static function concatIdentifiers ( array $columns )
        {
            $escaped = array_map(
                static fn ($column) => self::escapeIdentifier((string) $column),
                array_values($columns)
            );

            return "CONCAT_WS(' ', ".implode(', ', $escaped).")";
        }

        /**
         * Whitelist an ORDER BY direction. Anything that is not exactly ASC
         * collapses to the safe default DESC.
         */
        static function normalizeDirection ( string $direction )
        {
            return strtoupper(trim($direction)) === 'ASC' ? 'ASC' : 'DESC';
        }


        /**
         * Searching / Filtering
         *
         * Construct the WHERE clause for server-side processing SQL query.
         *
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here performance on large
         * databases would be very poor
         *
         *  @param  array $request Data sent to server by DataTables
         *  @param  array $columns Column information array
         *  @param  array $bindings Riceve i binding del termine (segnaposto
         *    `:binding_N`, vedi bind()): il termine non entra mai nel SQL.
         *  @return string SQL where clause
         */
        static function filter ( $request, $columns, array &$bindings )
        {

            if ( !isset($request['search']) || $request['search']['value'] === '' ) {
                return '';
            }

            $fields = is_array($columns) ? $columns : [];

            // Codifica di sanitize() conservata, barre di addslashes tolte: l'escape lo fa il binding.
            $where = self::buildSearchWhere( stripslashes(sanitize($request['search']['value'])), $fields, $bindings );

            return $where === '' ? '' : 'WHERE '.$where;

        }


        /**
         * Build the search WHERE body (without the leading "WHERE").
         *
         * @param string $searchValue Already-sanitized search string
         * @param array  $fields      Mixed list: string = main-table column;
         *   array = relation descriptor with keys table, local_key, foreign_key,
         *   columns[] and/or relations[] (nested descriptors, same shape)
         * @param array $bindings Ogni LIKE diventa un segnaposto distinto con
         *   valore `%termine%` (`%`, `_` e `\` del termine escapati).
         * @return string WHERE body, or '' when nothing is searchable
         */
        static function buildSearchWhere ( string $searchValue, array $fields, array &$bindings )
        {
            $searchValue = trim($searchValue);
            if ( $searchValue === '' ) {
                return '';
            }

            $mainCols  = [];
            $relations = [];

            foreach ( $fields as $field ) {
                if ( is_string($field) && $field !== '' ) {
                    $mainCols[] = $field;
                } elseif ( self::isRelation($field) ) {
                    $relations[] = $field;
                }
            }

            if ( $mainCols === [] && $relations === [] ) {
                return '';
            }

            $words   = preg_split('/\s+/', $searchValue, -1, PREG_SPLIT_NO_EMPTY);
            $perWord = [];

            // All identifiers below (columns, table, keys) come from the
            // client-round-tripped search_columns blob: escape every one so a
            // tampered identifier cannot break out of the quoted context.
            $mainConcat = $mainCols !== [] ? self::concatIdentifiers($mainCols) : '';

            foreach ( $words as $word ) {
                $ors = [];
                // `\` è il carattere di escape di LIKE: va raddoppiato come `%` e `_`.
                $value = '%'.addcslashes($word, '%_\\').'%';

                if ( $mainConcat !== '' ) {
                    $ors[] = "(".$mainConcat." LIKE ".self::bind($bindings, $value).")";
                }

                foreach ( $relations as $rel ) {
                    $ors[] = self::relationCondition($rel, $value, $bindings);
                }

                $perWord[] = count($ors) === 1 ? $ors[0] : '('.implode(' OR ', $ors).')';
            }

            return implode(' AND ', $perWord);

        }

        /**
         * A relation descriptor is usable when it names the table and both
         * keys, and has columns to search or at least one usable nested
         * relation.
         */
        static function isRelation ( $field )
        {
            if ( !is_array($field)
                || empty($field['table'])
                || empty($field['local_key'])
                || empty($field['foreign_key']) ) {
                return false;
            }

            if ( !empty($field['columns']) && is_array($field['columns']) ) {
                return true;
            }

            foreach ( (array) ($field['relations'] ?? []) as $child ) {
                if ( self::isRelation($child) ) {
                    return true;
                }
            }

            return false;
        }

        /**
         * `local_key IN (SELECT foreign_key FROM table WHERE ...)` for one
         * relation: its own columns and its nested relations, OR-ed, so the
         * search can walk movement -> version -> product. Every identifier is
         * escaped at every level. Ogni LIKE riceve un proprio segnaposto per
         * `$value`.
         */
        static function relationCondition ( array $relation, string $value, array &$bindings )
        {
            $conditions = [];

            if ( !empty($relation['columns']) && is_array($relation['columns']) ) {
                $conditions[] = self::concatIdentifiers($relation['columns'])." LIKE ".self::bind($bindings, $value);
            }

            foreach ( (array) ($relation['relations'] ?? []) as $child ) {
                if ( self::isRelation($child) ) {
                    $conditions[] = self::relationCondition($child, $value, $bindings);
                }
            }

            if ( $conditions === [] ) {
                return '';
            }

            $where = count($conditions) === 1 ? $conditions[0] : '('.implode(' OR ', $conditions).')';

            return "(".self::escapeIdentifier((string) $relation['local_key'])
                ." IN (SELECT ".self::escapeIdentifier((string) $relation['foreign_key'])
                ." FROM ".self::escapeIdentifier((string) $relation['table'])
                ." WHERE ".$where."))";
        }


        /**
         * Perform the SQL queries needed for an server-side processing requested,
         * utilising the helper functions of this class, limit(), order() and
         * filter() among others. The returned array is ready to be encoded as JSON
         * in response to an SSP request, or can be modified if needed before
         * sending back to the client.
         *
         *  @param  array $request Data sent to server by DataTables
         *  @param  array|mysqli $conn connessione mysqli o array di parametri
         *  @param  string $table SQL table to query
         *  @param  string $primaryKey Primary key of the table
         *  @param  array $columns Column information array
         *  @return array          Server-side processing response array
         */
        static function simple ( $request, $conn, $table, $primaryKey, $columns )
        {
            $bindings = array();
            $db = self::db( $conn );

            // Allow for a JSON string to be passed in
            if (isset($request['json'])) {
                $request = json_decode($request['json'], true);
            }

            // Build the SQL query string from the request
            $limit = self::limit( $request, $columns );
            $order = self::order( $request, $columns );
            $where = self::filter( $request, $columns, $bindings );

            // Main query to actually get the data
            $data = self::sql_exec( $db, $bindings,
                "SELECT `".implode("`, `", self::pluck($columns, 'db'))."`
                FROM `$table`
                $where
                $order
                $limit"
            );

            // Data set length after filtering
            $resFilterLength = self::sql_exec( $db, $bindings,
                "SELECT COUNT(`{$primaryKey}`)
                FROM   `$table`
                $where"
            );
            $recordsFiltered = $resFilterLength[0][0];

            // Total data set length
            $resTotalLength = self::sql_exec( $db,
                [],
                "SELECT COUNT(`{$primaryKey}`)
                FROM   `$table`"
            );
            $recordsTotal = $resTotalLength[0][0];

            /*
            * Output
            */
            return array(
                "draw"            => isset ( $request['draw'] ) ?
                    intval( $request['draw'] ) :
                    0,
                "recordsTotal"    => intval( $recordsTotal ),
                "recordsFiltered" => intval( $recordsFiltered ),
                "data"            => self::data_output( $columns, $data )
            );
        }


        /**
         * The difference between this method and the `simple` one, is that you can
         * apply additional `where` conditions to the SQL queries. These can be in
         * one of two forms:
         *
         * * 'Result condition' - This is applied to the result set, but not the
         *   overall paging information query - i.e. it will not effect the number
         *   of records that a user sees they can have access to. This should be
         *   used when you want apply a filtering condition that the user has sent.
         * * 'All condition' - This is applied to all queries that are made and
         *   reduces the number of records that the user can access. This should be
         *   used in conditions where you don't want the user to ever have access to
         *   particular records (for example, restricting by a login id).
         *
         * In both cases the extra condition can be added as a simple string, or if
         * you are using external values, as an assoc. array with `condition` and
         * `bindings` parameters. The `condition` is a string with the SQL WHERE
         * condition and `bindings` is an assoc. array of the binding names and
         * values.
         *
         *  @param  array $request Data sent to server by DataTables
         *  @param  array|mysqli $conn connessione mysqli o array di parametri
         *  @param  string $table SQL table to query
         *  @param  string $primaryKey Primary key of the table
         *  @param  array $columns Column information array
         *  @param  string|array $whereResult WHERE condition to apply to the result set
         *  @param  string|array $whereAll WHERE condition to apply to all queries
         *  @return array          Server-side processing response array
         */
        static function complex (
            $request,
            $conn,
            $table,
            $primaryKey,
            $columns,
            $searchField=null,
            $whereResult=null,
            $whereAll=null,
            $orderCol=null,
            $orderDir=null,
            $select=null,
            $groupBy=null
        ) {
            $db = self::db( $conn );

            $queries = self::complexQueries(
                new Query( $db ),
                $request,
                $table,
                $primaryKey,
                $columns,
                $searchField,
                $whereResult,
                $whereAll,
                $orderCol,
                $orderDir,
                $select,
                $groupBy
            );

            // Main query to actually get the data
            $data = self::sql_exec( $db, $queries['data'] );

            // Data set length after filtering
            $resFilterLength = self::sql_exec( $db, $queries['filtered'] );
            $recordsFiltered = $resFilterLength[0][0] ?? 0;

            // Total data set length
            $resTotalLength = self::sql_exec( $db, $queries['total'] );
            $recordsTotal = $resTotalLength[0][0] ?? 0;

            /*
            * Output
            */
            return array(
                "draw"            => isset ( $request['draw'] ) ?
                    intval( $request['draw'] ) :
                    0,
                "recordsTotal"    => intval( $recordsTotal ),
                "recordsFiltered" => intval( $recordsFiltered ),
                "data"            => self::data_output( $columns, $data )
            );
        }


        /**
         * Le tre query di complex() (dati, conteggio filtrato, conteggio totale)
         * già complete dei valori, senza eseguirle.
         *
         * Tabella e chiave primaria passano da Query::escapeIdentifier. Ogni
         * frammento (ricerca, whereResult, whereAll) viene legato da solo con
         * i propri binding tramite `$binder`: i frammenti firmati senza binding
         * non passano dallo scanner dei segnaposto. I binding della ricerca
         * non entrano mai nel conteggio totale.
         *
         * @return array{data:string,filtered:string,total:string}
         */
        static function complexQueries (
            Query $binder,
            $request,
            $table,
            $primaryKey,
            $columns,
            $searchField=null,
            $whereResult=null,
            $whereAll=null,
            $orderCol=null,
            $orderDir=null,
            $select=null,
            $groupBy=null
        ): array {
            $tableSql    = Query::escapeIdentifier( (string) $table );
            $pkSql       = Query::escapeIdentifier( (string) $primaryKey );
            $whereAllSql = '';

            // GROUP BY opzionale. $select e $groupBy sono frammenti SQL generati
            // server-side e firmati (ConfigCodec), verificati in
            // ListProvider::fetch prima di arrivare qui: stesso modello di
            // fiducia dei $whereResult/$whereAll, nessuna nuova superficie di
            // injection. Con il group by i conteggi contano i GRUPPI (subquery),
            // non le righe, e il SELECT diventa la lista di aggregati.
            $groupBy    = ( $groupBy !== null && trim((string) $groupBy) !== '' ) ? trim((string) $groupBy) : '';
            $selectList = ( $select  !== null && trim((string) $select)  !== '' ) ? trim((string) $select)  : '*';
            $groupSql   = $groupBy !== '' ? 'GROUP BY '.$groupBy : '';

            // Build the SQL query string from the request
            $limit = self::limit( $request, $columns );
            $order = self::order( $orderCol, $orderDir );

            $searchBindings = array();
            $where = self::bindFragment( $binder, self::filter( $request, $searchField, $searchBindings ), $searchBindings );

            // whereResult can be a simple string, or an assoc. array with a
            // condition and bindings
            if ( $whereResult ) {
                $str = self::conditionFragment( $binder, $whereResult );

                $where = $where ?
                    $where .' AND '.$str :
                    'WHERE '.$str;
            }

            // Likewise for whereAll
            if ( $whereAll ) {
                $str = self::conditionFragment( $binder, $whereAll );

                $where = $where ?
                    $where .' AND '.$str :
                    'WHERE '.$str;

                $whereAllSql = 'WHERE '.$str;
            }

            $data = "SELECT $selectList
                FROM $tableSql
                $where
                $groupSql
                $order
                $limit";

            if ( $groupBy !== '' ) {

                // Con il GROUP BY il conteggio delle righe è il numero di
                // GRUPPI: si avvolge la query raggruppata in una subquery.
                $filtered = "SELECT COUNT(*) FROM (SELECT 1 FROM $tableSql $where $groupSql) `wi_grouped`";
                $total    = "SELECT COUNT(*) FROM (SELECT 1 FROM $tableSql $whereAllSql $groupSql) `wi_grouped`";

            } else {

                $filtered = "SELECT COUNT($pkSql)
                    FROM   $tableSql
                    $where";

                $total = "SELECT COUNT($pkSql)
                    FROM   $tableSql ".
                    $whereAllSql;

            }

            return array(
                'data'     => $data,
                'filtered' => $filtered,
                'total'    => $total,
            );
        }

        /**
         * Condizione whereResult/whereAll: stringa, oppure
         * `['condition' => ..., 'bindings' => [...]]` legata con i suoi binding.
         */
        static function conditionFragment ( Query $binder, $condition ): string
        {
            if ( !is_array($condition) ) {
                return (string) $condition;
            }

            $bindings = array();

            if ( isset($condition['bindings']) ) {
                self::add_bindings( $bindings, $condition['bindings'] );
            }

            return self::bindFragment( $binder, (string) ($condition['condition'] ?? ''), $bindings );
        }

        /** Sostituisce i segnaposto del frammento; senza binding lo lascia intatto. */
        static function bindFragment ( Query $binder, string $sql, array $bindings ): string
        {
            if ( $bindings === array() ) {
                return $sql;
            }

            return $binder->bind( $sql, self::bindParams($bindings) );
        }


        /**
         * Connessione da array di parametri (host, db, user, pass, charset
         * opzionale). Nessun valore deve arrivare dalla richiesta.
         *
         * @param  array $sql_details SQL server connection details array, with the
         *   properties:
         *     * host - host name
         *     * db   - database name
         *     * user - user name
         *     * pass - user password
         * @return mysqli Database connection handle
         */
        static function sql_connect ( $sql_details )
        {
            try {
                $db = @new mysqli(
                    (string) ($sql_details['host'] ?? ''),
                    (string) ($sql_details['user'] ?? ''),
                    (string) ($sql_details['pass'] ?? ''),
                    (string) ($sql_details['db'] ?? '')
                );
            }
            catch (Throwable $e) {
                self::fatal(
                    "An error occurred while connecting to the database. ".
                    "The error reported by the server was: ".$e->getMessage()
                );
            }

            if ( $db->connect_errno ) {
                self::fatal(
                    "An error occurred while connecting to the database. ".
                    "The error reported by the server was: ".$db->connect_error
                );
            }

            $charset = trim( (string) ($sql_details['charset'] ?? self::defaultCharset()) );

            try {
                $charsetSet = $db->set_charset( $charset !== '' ? $charset : 'latin1' );
            }
            catch (Throwable $e) {
                $charsetSet = false;
            }

            if ( !$charsetSet ) {
                self::fatal( "Unable to set the connection charset '{$charset}': ".$db->error );
            }

            return $db;
        }

        /** Charset delle connessioni del framework (DB_CHARSET, default latin1). */
        private static function defaultCharset (): string
        {
            try {
                $charset = trim( (string) (Credentials::database()->charset ?? '') );
            }
            catch (Throwable $e) {
                $charset = '';
            }

            return $charset !== '' ? $charset : 'latin1';
        }


        /**
         * Execute an SQL query on the database
         *
         * @param  mysqli $db  Database handler
         * @param  array    $bindings Binding da bind()/add_bindings(), sostituiti
         *   con Query::bind() prima dell'esecuzione. Note that this can be given
         *   as the SQL query string if no bindings are required.
         * @param  string   $sql SQL query to execute.
         * @return array         Result from the query (all rows)
         */
        static function sql_exec ( $db, $bindings, $sql=null )
        {
            // Argument shifting
            if ( $sql === null ) {
                $sql = $bindings;
                $bindings = array();
            }

            try {
                if ( is_array($bindings) && $bindings !== array() ) {
                    $sql = ( new Query($db) )->bind( (string) $sql, self::bindParams($bindings) );
                }

                $result = $db->query( (string) $sql );
            }
            catch (Throwable $e) {
                self::fatal( "An SQL error occurred: ".$e->getMessage() );
            }

            if ( $result === false ) {
                self::fatal( "An SQL error occurred: ".$db->error );
            }

            if ( !$result instanceof mysqli_result ) {
                return array();
            }

            // Return all
            $rows = self::nativeTypes( $result, $result->fetch_all( MYSQLI_BOTH ) );
            $result->free();

            return $rows;
        }

        /**
         * Interi e float come tipi PHP nativi, come li restituiva pdo_mysql
         * (prepare emulate, PHP >= 8.1); DECIMAL e il resto restano stringhe.
         */
        static function nativeTypes ( mysqli_result $result, array $rows ): array
        {
            $casts = array();
            $lastIndex = array();

            foreach ( $result->fetch_fields() as $index => $field ) {
                $kind = self::nativeKind( (int) $field->type );
                $lastIndex[$field->name] = $index;

                if ( $kind !== null ) {
                    $casts[$index] = array( $kind, $field->name );
                }
            }

            if ( $casts === array() ) {
                return $rows;
            }

            foreach ( $rows as &$row ) {
                foreach ( $casts as $index => [ $kind, $name ] ) {
                    if ( !isset($row[$index]) ) {
                        continue;
                    }

                    $row[$index] = self::castNative( $row[$index], $kind );

                    // Con nomi duplicati la chiave associativa è dell'ultima colonna.
                    if ( $lastIndex[$name] === $index ) {
                        $row[$name] = $row[$index];
                    }
                }
            }
            unset($row);

            return $rows;
        }

        static function nativeKind ( int $type ): ?string
        {
            if ( in_array($type, array( MYSQLI_TYPE_TINY, MYSQLI_TYPE_SHORT, MYSQLI_TYPE_LONG, MYSQLI_TYPE_LONGLONG, MYSQLI_TYPE_INT24, MYSQLI_TYPE_YEAR ), true) ) {
                return 'int';
            }

            if ( in_array($type, array( MYSQLI_TYPE_FLOAT, MYSQLI_TYPE_DOUBLE ), true) ) {
                return 'float';
            }

            return null;
        }

        static function castNative ( $value, string $kind )
        {
            if ( !is_string($value) ) {
                return $value;
            }

            if ( $kind === 'int' ) {
                // Oltre PHP_INT_MAX (BIGINT UNSIGNED) resta stringa.
                return preg_match('/^-?\d+\z/', $value) === 1 && (string) (int) $value === $value ? (int) $value : $value;
            }

            return is_numeric($value) ? (float) $value : $value;
        }


        /* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
        * Internal methods
        */

        /**
         * Risposta JSON di errore: messaggio generico, dettaglio solo in debug.
         *
         * @return array{error:string,detail?:string}
         */
        static function fatalPayload ( string $public, ?string $detail = null, ?bool $debug = null ): array
        {
            $payload = array( 'error' => $public );

            if ( $detail !== null && $detail !== '' && ( $debug ?? Debug::enabled() ) ) {
                $payload['detail'] = $detail;
            }

            return $payload;
        }

        /**
         * Throw a fatal error.
         *
         * Scrive il dettaglio nel log (`backend` / `list.table`) e risponde con
         * il JSON di fatalPayload(), che DataTables mostra all'utente.
         *
         * @param  string $msg Dettaglio dell'errore (non mostrato fuori dal debug)
         */
        static function fatal ( $msg ): never
        {
            $detail = (string) $msg;

            try {
                Logger::log( new RuntimeException($detail), 'backend', 'list.table', 'ERROR', 'error', [], false );
            }
            catch (Throwable $e) {
                // Il log non deve impedire la risposta.
            }

            echo json_encode( self::fatalPayload( self::FATAL_MESSAGE, $detail ) );

            exit(0);
        }

        /**
         * Create a binding key which can be used for escaping variables safely
         * when executing a query with sql_exec()
         *
         * @param  array &$a    Array of bindings
         * @param  mixed  $val  Value to bind
         * @param  mixed  $type Ignorato (era il tipo PDO), tenuto per compatibilità
         * @return string       Bound key to be used in the SQL where this parameter
         *   would be used.
         */
        static function bind ( &$a, $val, $type = null )
        {
            $key = ':binding_'.count( $a );

            $a[] = array(
                'key' => $key,
                'val' => $val,
                'type' => $type
            );

            return $key;
        }

        /**
         * Aggiunge a `$a` i binding di una condizione: mappa `chiave => valore`
         * (chiavi con o senza `:`, intere per `?`) o il vecchio formato
         * `['bindings' => [...]]`.
         */
        static function add_bindings(&$a, $vals)
        {
            if ( is_array($vals) && isset($vals['bindings']) && is_array($vals['bindings']) ) {
                $vals = $vals['bindings'];
            }

            foreach ( (array) $vals as $key => $value ) {
                $a[] = array(
                    'key'  => is_int($key) ? $key : ':'.ltrim((string) $key, ':'),
                    'val'  => $value,
                    'type' => null
                );
            }
        }

        /**
         * Binding nel formato di Query::bind(): chiavi intere in ordine per `?`,
         * nomi senza `:` per i segnaposto con nome.
         */
        static function bindParams ( array $bindings ): array
        {
            $params = array();

            foreach ( $bindings as $binding ) {
                $key = $binding['key'] ?? null;

                if ( $key === null || is_int($key) ) {
                    $params[] = $binding['val'] ?? null;
                } else {
                    $params[ltrim((string) $key, ':')] = $binding['val'] ?? null;
                }
            }

            return $params;
        }


        /**
         * Pull a particular property from each assoc. array in a numeric array, 
         * returning and array of the property values from each item.
         *
         *  @param  array  $a    Array to get data from
         *  @param  string $prop Property to read
         *  @return array        Array of property values
         */
        static function pluck ( $a, $prop, $table)
        {

            $out = array();

            for ( $i=0, $len=count($a) ; $i<$len ; $i++ ) {
                
                if ( 
                    (empty($a[$i][$prop]) && 
                    $a[$i][$prop] !== 0) || 
                    !sqlColumnExists($table, $a[$i][$prop])
                    ) {
                    continue;
                }

                //removing the $out array index confuses the filter method in doing proper binding,
                //adding it ensures that the array data are mapped correctly
                $out[$i] = $a[$i][$prop];
            }

            return $out;
        }


        /**
         * Return a string from an array or a string
         *
         * @param  array|string $a Array to join
         * @param  string $join Glue for the concatenation
         * @return string Joined string
         */
        static function _flatten ( $a, $join = ' AND ' )
        {
            if ( ! $a ) {
                return '';
            }
            else if ( is_array($a) ) {
                return implode( $join, $a );
            }
            return $a;
        }
    }