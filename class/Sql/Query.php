<?php

    namespace Wonder\Sql;

    use Wonder\Sql\Connection;
    use Wonder\Sql\Utility\Error;

    use mysqli;

    class Query {

        public mysqli $mysqli;

        function __construct( ?mysqli $connection = null ) 
        { 
            
            $this->mysqli = ($connection === null) ? Connection::Connect('main') : $connection; 
        
        }

        public static function Conditions( string | array $conditions, bool $where = true ): string
        {

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

                        foreach ($value as $v) {$filter .= "'$v', ";}
                        $filter = substr($filter, 0, -2); 

                        $filter .= ") AND ";

                    } else {

                        $filter .= "$label = '$value' AND ";

                    }
                }

                $filter = substr($filter, 0, -4);

            } else {

                if (str_contains($conditions, "WHERE") || str_contains($conditions, "where")) {
                    $filter = $where ? $conditions : str_replace( [ "WHERE", "where" ], "", $conditions);
                } else {
                    $filter .= $conditions;
                }

            }

            return " $filter";

        }

        public function Values( array $values, bool $correlated = false ): string
        {

            if ($correlated) {
                
                $query = "";

                foreach ($values as $l => $v) {
            
                    if ($v != '' || $v == 0) { $query .= "`$l` = '$v', "; } 
                    else { $query .= "`$l` = NULL, ";  }

                }

                return substr($query, 0, -2);

            } else {

                $label = "";
                $value = "";

                foreach ($values as $l => $v) {

                    $label .= "`$l`, ";
                    if ($v != '' || $v == 0) { $value .= "'$v', "; } 
                    else { $value .= "NULL, "; } 

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
            
            foreach ($tables as $t => $k) { 

                if ($i == 0) {

                    $tableJoined .= $t;

                    $mainTable = $t;
                    $mainKey = $k;

                } else {

                    $tableJoined .= " JOIN $t ON $mainTable.$mainKey = $t.$k"; 

                }
            
                $i++;

            }
    
            return $tableJoined;

        }

        public function Insert( string $table, string | array $values ): object
        {

            $query = "INSERT INTO `$table` ";
            $query .= $this->Values( $values );

            $RETURN = (object) [];
            $RETURN->table = $table;
            $RETURN->query = $query;

            if (!$this->mysqli->query( $query )) {
                
                new Error( 'Insert', $table, $query, $this->mysqli );

            } else {

                $RETURN->insert_id = $this->mysqli->insert_id;

            }
            

            return $RETURN;

        }

        public function Update( string $table, array $values, string $column, string | int $value ): object 
        {

            $query = "UPDATE `$table` SET ";
            $query .= $this->Values( $values, true );
            $query .= " WHERE `$column` = '$value'";

            if (!$this->mysqli->query( $query )) { new Error( 'Update', $table, $query, $this->mysqli ); }
            
            $RETURN = (object) [];
            $RETURN->table = $table;
            $RETURN->query = $query;

            return $RETURN;


        }

        public function Select( string | array $table, string | array $condition = null, string | int $limit = null, string $order = null, string $orderDirection = null, string | array $attributes = '*' ) 
        {

            $query = "SELECT ";
            $query .= is_array($attributes) ? implode(",", $attributes) : $attributes;
            $query .= " FROM ";
            $query .= is_array($table) ? self::JoinTable( $table ) : "`$table`";
            $query .= ($condition == null) ? "" : self::Conditions( $condition );
            $query .= ($order == null) ? "" : " ORDER BY $order";
            $query .= ($orderDirection == null) ? "" : " $orderDirection";
            $query .= ($limit == null) ? "" : " LIMIT $limit";

            if (!$RESULT = $this->mysqli->query( $query )) {
                
                new Error( 'Select', $table, $query, $this->mysqli );

            } else {

                $RETURN = (object) [];

                $RETURN->exists = ($RESULT->num_rows > 0) ? true : false;
                $RETURN->Nrow = $RESULT->num_rows;
                $RETURN->row = [];

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
                
                return $RETURN;

            }
        
        }

        public function Delete( string $table, string | array $condition = null ): string
        {

            $query = "DELETE FROM ";
            $query .= "`$table`";
            $query .= ($condition == null) ? "" : self::Conditions( $condition );

            if (!$RESULT = $this->mysqli->query( $query )) {
                
                new Error( 'Delete', $table, $query, $this->mysqli );

            }

            return $RESULT;

        }

        public function Truncate( string $table ): string
        {

            $query = "TRUNCATE TABLE ";
            $query .= "`$table`";

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

        public function Sum( string $table, string | array $query, string $column = '*' ): int
        {

            $ATTRIBUTES = "SUM($column)";
            $n = $this->Select( $table, $query, null, null, null, $ATTRIBUTES )->row[0][$ATTRIBUTES];
    
            return empty($n) ? 0 : $n;
    
        }

        public function Count( string $table, string | array $query = null, string $column = '*', bool $distinct = false ): int
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
            $query .= "'$name'";

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
            $query .= "'$name'";

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
            $query .= "`$table` ";
            $query .= "LIKE ";
            $query .= "'$column'";

            if (!$RESULT = $this->mysqli->query( $query )) {
                
                new Error( 'ColumnExists', $table, $query, $this->mysqli );
                return false;

            } else {

                return ($RESULT->num_rows === 0) ? false : true;

            }

        }

        /**
         * 
         * Connessione al database information_schema
         * 
         * @return Query
         * 
         */
        function ISConnection() : Query
        { 

            return new Query(Connection::Connect('information_schema')); 

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

        function ColumnForeign( string $table, string $column = null ) : array
        {

            $condition = [
                'table_schema' => $this->GetDatabase(),
                'table_name' => $table
            ];

            if ($column != null) {
                $condition['column_name'] = $column;
            }

            return array_column(
                $this->ISConnection()->Select( 'key_column_usage', $condition, null, null, null, 'constraint_name as cName' )->row,
                'cName'
            );

        }

        function TableIndex( string $table, string $column = null  )
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