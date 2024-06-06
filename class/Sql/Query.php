<?php

    namespace Wonder\Sql;

    use Wonder\Sql\Utility\Error;

    class Query {

        public object $mysqli;

        function __construct( $connection ) { $this->mysqli = $connection; }

        public function Conditions( $conditions ) {

            $filter = "";

            $filter .= "WHERE ";
        
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
                    $filter = $conditions;
                } else {
                    $filter .= $conditions;
                }

            }

            return " ".$filter;

        }

        public function Values( $values, $correlated = false ) {

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

        public function JoinTable( $tables ) {
    
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

        public function Insert( string $table, string | array $values ) {

            $query = "INSERT INTO `$table` ";
            $query .= $this->Values( $values );

            if (!$this->mysqli->query( $query )) {
                
                new Error( 'Insert', $table, $query, $this->mysqli );

            } else {

                $RETURN = (object) [];
                $RETURN->table = $table;
                $RETURN->query = $query;
                $RETURN->insert_id = $this->mysqli->insert_id;

                return $RETURN;

            }

        }

        public function Update( $table, $values, $column, $value ) {

            $query = "UPDATE `$table` SET ";
            $query .= $this->Values( $values, true );
            $query .= " WHERE `$column` = '$value'";

            if (!$this->mysqli->query( $query )) {
                
                new Error( 'Update', $table, $query, $this->mysqli );

            } else {

                $RETURN = (object) [];
                $RETURN->table = $table;
                $RETURN->query = $query;

                return $RETURN;

            }

        }

        public function Select( $table, $condition = null, $limit = null, $order = null, $orderDirection = null, $attributes = '*' ) {

            $query = "SELECT ";
            $query .= $attributes;
            $query .= " FROM ";
            $query .= is_array($table) ? $this->JoinTable( $table ) : "`$table`";
            $query .= ($condition == null) ? "" : $this->Conditions( $condition );
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
                        $RETURN->id = isset($RETURN->row['id']) ? $RETURN->row['id'] : "";
                    } else {
                        $RETURN->row = $RESULT->fetch_all( MYSQLI_ASSOC );
                        $RETURN->id = isset($RETURN->row[0]['id']) ? $RETURN->row[0]['id'] : "";
                    }

                } else if ($RESULT->num_rows >= 2) {

                    $RETURN->row = $RESULT->fetch_all( MYSQLI_ASSOC );

                }
                
                return $RETURN;

            }
        
        }

        public function Delete( $table, $condition = null ) {

            $query = "DELETE FROM ";
            $query .= "`$table`";
            $query .= ($condition == null) ? "" : $this->Conditions( $condition );

            if (!$RESULT = $this->mysqli->query( $query )) {
                
                new Error( 'Delete', $table, $query, $this->mysqli );

            } else {

                return $RESULT;

            }

        }

        public function Truncate( $table ) {

            $query = "TRUNCATE TABLE ";
            $query .= "`$table`";

            if (!$RESULT = $this->mysqli->query( $query )) {
                
                new Error( 'Truncate', $table, $query, $this->mysqli );

            } else {

                return $RESULT;

            }

        }

        /**
         * 
         * EXTRA FUNCTIONS
         * 
         */

        public function GetDatabase() {

            $query = "SELECT DATABASE() AS db";

            if (!$RESULT = $this->mysqli->query( $query )) {
                
                new Error( 'GetDatabase', '', $query, $this->mysqli );

            } else {

                $row = $RESULT->fetch_assoc();

                return $row['db'];

            }

        }

        public function Sum( $table, $query, $column = '*' ) {

            $ATTRIBUTES = "SUM($column)";
            $n = $this->Select( $table, $query, null, null, null, $ATTRIBUTES )->row[0][$ATTRIBUTES];
    
            return empty($n) ? 0 : $n;
    
        }

        public function Count( $table, $query = null, $column = '*', $distinct = false ) {

            $DISTINCT = $distinct ? "DISTINCT " : "";
            $ATTRIBUTES = "COUNT($DISTINCT$column)";
            $n = $this->Select($table, $query, null, null, null, $ATTRIBUTES)->row[0][$ATTRIBUTES];
    
            return empty($n) ? 0 : $n;

        }


        /**
         * 
         * Function for verify if database or table or table column exists
         * 
         */

        public function DatabaseExists( $name ) {

            $query = "SHOW DATABASES LIKE ";
            $query .= "'$name'";

            if (!$RESULT = $this->mysqli->query( $query )) {
                
                new Error( 'DatabaseExists', '', $query, $this->mysqli );

            } else {

                return ($RESULT->num_rows === 0) ? false : true;

            }

        }

        public function TableExists( $name ) {

            $query = "SHOW TABLES LIKE ";
            $query .= "'$name'";

            if (!$RESULT = $this->mysqli->query( $query )) {
                
                new Error( 'TableExists', '', $query, $this->mysqli );

            } else {

                return ($RESULT->num_rows === 0) ? false : true;

            }

        }

        public function ColumnExists( $table, $column ) {

            $query = "SHOW COLUMNS FROM ";
            $query .= "`$table` ";
            $query .= "LIKE ";
            $query .= "'$column'";

            if (!$RESULT = $this->mysqli->query( $query )) {
                
                new Error( 'ColumnExists', $table, $query, $this->mysqli );

            } else {

                return ($RESULT->num_rows === 0) ? false : true;

            }

        }

    }