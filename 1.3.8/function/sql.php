<?php

    function sqlError($FUNCTION, $TABLE, $QUERY, $ERROR_N, $ERROR) {

        global $DEBUG_MODE;
        global $ERROR;

        if ($DEBUG_MODE) {
                
            echo "Funzione: <b>$FUNCTION</b><br>";
            echo "Table: <b>$TABLE</b><br>";
            echo "Query: <b>$QUERY</b><br>";
            echo "<br>";
            echo "Error N°$ERROR_N<br>";
            echo "$ERROR<br>";
            echo "<br>";

        }else{

            if ($FUNCTION == 'sqlTable') { $ALERT = 951; } 
            elseif ($FUNCTION == 'sqlInsert') { $ALERT = 952; }
            elseif ($FUNCTION == 'sqlModify') { $ALERT = 953; }
            elseif ($FUNCTION == 'sqlSelect') { $ALERT = 954; }

            $values = [
                "function" => $FUNCTION ,
                "table" => $TABLE,
                "query" => $QUERY,
                "error_n" => $ERROR_N,
                "error" => $ERROR
            ];

            sqlInsert('sql_error', $values);
            
        }

    }

    /**
    *   
    *   $column = 
    *       'name' => [
    *        'type' => string,
    *        'length' => int,
    *        'null' => bolean,
    *        'default' => NULL | DEFAULT
    *    ]
    *    
    */ 

    function sqlTable($TABLE, $COLUMN, $ENGINE = "MyISAM", $CHARSET = "latin1") {

        global $mysqli;
        global $MYSQLI_CONNECTION;

        $def_mysqli = $mysqli;

        if (isset($COLUMN['DATABASE'])) {

            $mysqli = $MYSQLI_CONNECTION[$COLUMN['DATABASE']];
            unset($COLUMN['DATABASE']);

        }

        if (sqlTableExists($TABLE)) {

            $QUERY = "";
            $columnBefore = "id";

            foreach ($COLUMN as $name => $value) {

                $name = strtolower($name);
                $defaultLenght = null;

                // Cambia colonna
                // Elimina colonna se non c'è nell'array
                
                if (!sqlColumnExists($TABLE, $name)) {

                    if ($name == 'position') {
                        $defaultType = 'INT';
                    } else {
                        $defaultType = 'VARCHAR';
                    }

                    $type = empty($value['sql']['type']) ? $defaultType : strtoupper($value['sql']['type']);
                
                    if ($type == "VARCHAR") { $defaultLenght = 1000; } elseif ($type == "BIGINT") { $defaultLenght = 11; } elseif ($type == "INT") { $defaultLenght = 11; }
    
                    $length = empty($value['sql']['length']) ? $defaultLenght : $value['sql']['length'];
                    $null = $value['sql']['null'] = true ? "NULL" : "NOT NULL";
                    $default = empty($value['sql']['default']) ? '' : "DEFAULT '".$value['sql']['default']."'";
                    
                    if ($length == null) {
                        $QUERY .= "ADD `$name` $type $null $default AFTER `$columnBefore`, ";
                    } else {
                        $QUERY .= "ADD `$name` $type($length) $null $default AFTER `$columnBefore`, ";
                    }
                    
                }

                $columnBefore = $name;

            }

            if (!empty($QUERY)) {

                $QUERY = substr($QUERY, 0, -2);
                $QUERY = "ALTER TABLE `$TABLE` $QUERY";

            }

        } else {

            $QUERY = "CREATE TABLE IF NOT EXISTS `$TABLE` ";
            $QUERY .= "( ";
            $QUERY .= "`id` INT NOT NULL AUTO_INCREMENT, ";

            foreach ($COLUMN as $name => $value) {
                
                $name = strtolower($name);
                $defaultLenght = null;

                if ($name == 'position') {
                    $defaultType = 'INT';
                } else {
                    $defaultType = 'VARCHAR';
                }

                $type = empty($value['sql']['type']) ? $defaultType : strtoupper($value['sql']['type']);
                
                if ($type == "VARCHAR") { $defaultLenght = 1000; } elseif ($type == "BIGINT") { $defaultLenght = 11; } elseif ($type == "INT") { $defaultLenght = 11; }

                $length = empty($value['sql']['length']) ? $defaultLenght : $value['sql']['length'];
                $null = $value['sql']['null'] = true ? "NULL" : "NOT NULL";
                $default = empty($value['sql']['default']) ? '' : "DEFAULT '".$value['sql']['default']."'";
                
                if ($length == null) {
                    $QUERY .= "`$name` $type $null $default, ";
                } else {
                    $QUERY .= "`$name` $type($length) $null $default, ";
                }

            }

            $QUERY .= "`deleted` VARCHAR(5) NOT NULL DEFAULT 'false', ";
            $QUERY .= "`last_modified` DATETIME on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ";
            $QUERY .= "`creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ";
            $QUERY .= "PRIMARY KEY (`id`) ";
            $QUERY .= ") ";
            $QUERY .= "ENGINE = $ENGINE ";
            $QUERY .= "DEFAULT CHARSET = $CHARSET ";
            $QUERY .= ";";

        }

        if (!empty($QUERY)) {

            if($mysqli->query($QUERY)) {

                $RETURN =  (object) array();
                $RETURN->table = $TABLE;
                $RETURN->query = $QUERY;
    
                $mysqli = $def_mysqli;
                
                return $RETURN;
                
            } else {
                

                $mysqli = $def_mysqli;

                sqlError('sqlTable', $TABLE, $QUERY, $mysqli->errno, $mysqli->error);
    
            }

        }

        $mysqli = $def_mysqli;

    }

    function sqlInsert($TABLE, $LABEL_VALUES) {

        global $mysqli;

        $QUERY = "INSERT INTO `$TABLE` ";
        
        $labels = "";
        $values = "";

        foreach ($LABEL_VALUES as $label => $value) {

            $labels .= "`$label`, ";
            if ($value != '' || $value == 0) { $values .= "'$value', "; } 
            else { $values .= "NULL, "; } 

        }

        $labels = substr($labels, 0, -2);
        $values = substr($values, 0, -2);

        $QUERY .= "($labels) VALUES ($values)";

        if (!$mysqli->query($QUERY)) {

            sqlError('sqlInsert', $TABLE, $QUERY, $mysqli->errno, $mysqli->error);

        } else {

            $RETURN = (object) array();
            $RETURN->table = $TABLE;
            $RETURN->query = $QUERY;
            $RETURN->insert_id = $mysqli->insert_id;

            return $RETURN;

        }

    }

    function sqlModify($TABLE, $LABEL_VALUES, $COLUMN, $VALUE) {

        global $mysqli;

        $QUERY = "UPDATE `$TABLE` SET ";

        foreach ($LABEL_VALUES as $label => $value) {
            
            if ($value != '' || $value == 0) { $QUERY .= "`$label` = '$value', "; } 
            else { $QUERY .= "`$label` = NULL, ";  }

        }

        $QUERY = substr($QUERY, 0, -2);
        $QUERY .= " WHERE `$COLUMN` = '$VALUE'";

        if (!$mysqli->query($QUERY)) {

            sqlError('sqlModify', $TABLE, $QUERY, $mysqli->errno, $mysqli->error);

        } else {

            $RETURN = (object) array();
            $RETURN->table = $TABLE;
            $RETURN->query = $QUERY;

            return $RETURN;

        }

    }

    function sqlSelect($table, $query = null, $limit = null, $order = null, $orderDirection = null, $attributes = '*') {

        global $mysqli;

        $filter = '';

        if (is_array($table)) {

            $tables = "";

            $mainTable = "";
            $mainKey = "";
            
            $i = 0;
            foreach ($table as $t => $k) { 

                if ($i == 0) {

                    $tables .= $t;

                    $mainTable = $t;
                    $mainKey = $k;

                } else {

                    $tables .= " JOIN $t ON $mainTable.$mainKey = $t.$k"; 

                }
            
                $i++;

            }
    
            $table = $tables;
            
        } else {

            $table = "`$table`";

        }

        if ($query != null) {

            $filter .= "WHERE ";
        
            if (is_array($query)) {

                foreach ($query as $label => $value) {

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

            }else{

                if (str_contains($query, "WHERE") || str_contains($query, "where")) {
                    $filter = $query;
                } else {
                    $filter .= $query;
                }

            }
             

        }  

        if ($order != null) { $filter .= " ORDER BY $order"; }
        if ($orderDirection != null) { $filter .= " $orderDirection"; }
        if ($limit != null) { $filter .= " LIMIT $limit"; }

        $sql = "SELECT $attributes FROM $table $filter";
        $result = $mysqli->query($sql);
        $Nrow = mysqli_num_rows($result);

        $return = (object) array();
        if ($Nrow == 1) {

            $return->exists = true;
            $return->Nrow = 1;
            $return->row = [];

            if ($limit == 1) {
                while ($row = $result->fetch_assoc()) {
                    $return->id = isset($row['id']) ? $row['id'] : "";
                    $return->row = $row;
                }
            } else {
                while ($row = $result->fetch_assoc()) {
                    $return->id = isset($row['id']) ? $row['id'] : "";
                    array_push($return->row, $row);
                }
            }
            
        }elseif ($Nrow >= 2) {
            $return->exists = true;
            $return->Nrow = $Nrow;
            $return->row = [];
            while ($row = $result->fetch_assoc()) {
                array_push($return->row, $row);
            }
        }else{
            $return->exists = false;
            $return->Nrow = 0;
            $return->row = [];
        }

        return $return;

    }

    function sqlDelete($table, $query = null) {
        
        global $mysqli;

        $filter = '';

        if ($query != null) {

            $filter .= "WHERE ";
        
            if (is_array($query)) {

                foreach ($query as $label => $value) {
                    if (is_array($value)) {
    
                        $filter .= "`$label` IN (";
    
                        foreach ($value as $v) {$filter .= "'$v', ";}
                        $filter = substr($filter, 0, -2); 
    
                        $filter .= ") AND ";
    
                    }else{
                        $filter .= "`$label` = '$value' AND ";
                    }
                }
    
                $filter = substr($filter, 0, -4);

            }else{

                $filter .= "$query";

            }
             
        }  

        $sql = "DELETE FROM `$table` $filter";
        $result = $mysqli->query($sql);

        return $result;

    }

    function sqlTruncate($table) {
        
        global $mysqli;

        $sql = "TRUNCATE TABLE `$table`";
        $result = $mysqli->query($sql);

        return $result;

    }

    function sqlCount($table, $query = null, $column = '*', $distinct = false) {

        $DISTINCT = $distinct ? "DISTINCT " : "";
        $ATTRIBUTES = "COUNT($DISTINCT$column)";
        return sqlSelect($table, $query, null, null, null, $ATTRIBUTES)->row[0][$ATTRIBUTES];

    }

    function sqlTableInfo($table, $database = 'main') {

        global $DB;
        global $mysqli;
        global $MYSQLI_CONNECTION;

        $def_mysqli = $mysqli;

        $DATABASE = $DB->database[$database];
        $mysqli = $MYSQLI_CONNECTION['information_schema'];

        $RETURN = (object) array();;

        foreach (sqlSelect('TABLES', [ 'TABLE_SCHEMA' => $DATABASE, 'TABLE_NAME' => $table ])->row[0] as $column => $value) {
            $column = strtolower($column);
            $RETURN->$column = $value;
        }

        $mysqli = $def_mysqli;

        return $RETURN;

    }

    function sqlTableExists($TABLE) {

        global $mysqli;

        $result = $mysqli->query("SHOW TABLES LIKE '$TABLE'");
        return (mysqli_num_rows($result)) ? true : false;
        
    }

    function sqlDatabaseExists($DATABASE) {

        global $mysqli;

        $result = $mysqli->query("SHOW DATABASES LIKE '$DATABASE'");
        return (mysqli_num_rows($result)) ? true : false;

        
    }

    function sqlColumnExists($TABLE, $COLUMN) {

        global $mysqli;

        $result = $mysqli->query("SHOW COLUMNS FROM `$TABLE` LIKE '$COLUMN'");
        return (mysqli_num_rows($result)) ? true : false;
        
    }

    function formToArray($table, $post, $tableFields, $OLD_VALUES = null) {
       
        global $NAME;
        global $ALERT;
        global $PATH;
        
        $VALUES = [];

        foreach ($tableFields as $name => $value) {

            $RULES = isset($value['input']) ? $value['input'] : [];

            if ($OLD_VALUES == null) {
                $ACTION = 'add';
                if (isset($RULES['add'])) {
                    $CONTINUE = is_array($RULES['add']) || $RULES['add'] == true ? true : false;
                }else{
                    $CONTINUE = true;
                }
            } else {
                $ACTION = 'modify';
                $VALUES['id'] = $OLD_VALUES['id'];
                if (isset($RULES['modify'])) {
                    $CONTINUE = is_array($RULES['modify']) || $RULES['modify'] == true ? true : false;
                }else{
                    $CONTINUE = true;
                }
            }

            if (isset($post[$name]) && $CONTINUE) {

                $VALUE = $post[$name];

                if ($OLD_VALUES != null) {
                    $OLD_VALUE = $OLD_VALUES[$name];
                }

                if (is_array($VALUE)) {
                    
                    if (isset($RULES['format']['file']) && $RULES['format']['file'] === true && count($VALUE['name']) > 0) {

                        if (isset($OLD_VALUE)) {
                            $ARRAY_VALUES = json_decode($OLD_VALUE, true);
                        } else {
                            $ARRAY_VALUES = [];
                        }

                        $VALUE = uploadFiles($VALUE, $RULES['format'], $PATH->rUpload.'/'.$NAME->folder, $ARRAY_VALUES);
                    
                    } else {

                        foreach ($VALUE as $k => $v) { if (empty($v) && $v != 0) { unset($VALUE[$k]); } }
                        $VALUE = json_encode(array_values($VALUE));

                    }
                    
                } else {
                    
                    if (isset($RULES['format']['lower']) && $RULES['format']['lower'] === true) {
                        $VALUE = strtolower($VALUE);
                    }
    
                    if (isset($RULES['format']['upper']) && $RULES['format']['upper'] === true) {
                        $VALUE = strtoupper($VALUE);
                    }
    
                    if (isset($RULES['format']['ucwords']) && $RULES['format']['ucwords'] === true) {
                        $VALUE = ucwords($VALUE);
                    }
    
                    if (isset($RULES['format']['unique']) && $RULES['format']['unique'] === true) {

                        $id = ($OLD_VALUES == null) ? null : $OLD_VALUES['id'];

                        if (!unique($VALUE, $table, $name, $id)) {
                            if ($name == 'link') { $ALERT = 971;} 
                            elseif ($name == 'code') { $ALERT = 972;}
                            elseif ($name == 'email') { $ALERT = 973;}
                            elseif ($name == 'username') { $ALERT = 974;}
                            elseif ($name == 'tel' || $name == 'tell') { $ALERT = 975;}
                            elseif ($name == 'phone' || $name == 'cel' || $name == 'cell') { $ALERT = 976;}
                            else { $ALERT = 970;}
                        }

                    }
    
                    if (isset($RULES['format']['link']) && $RULES['format']['link'] === true) {
                        $VALUE = create_link($VALUE);
                    }
    
                    if (isset($RULES['format']['link_unique']) && $RULES['format']['link_unique'] === true) {
                        if ($OLD_VALUES == null) {
                            $VALUE = create_link($VALUE, $table, $name);
                        } else {
                            $VALUE = create_link($VALUE, $table, $name, $OLD_VALUES['id']);
                        }
                    }
    
                    if (!isset($RULES['format']['sanitize']) || $RULES['format']['sanitize'] != false) {
                        $VALUE = sanitize($VALUE);
                    }
    
                    if (isset($RULES['format']['sanitizeFirst']) && $RULES['format']['sanitizeFirst'] === true) {
                        $VALUE = sanitizeFirst($VALUE);
                    }
    
                    if (isset($RULES['format']['date']) && $RULES['format']['date'] === true) {
                        if (!empty($VALUE)) {
                            $VALUE = str_replace('/', '-',$VALUE);
                            $VALUE = date('Y-m-d H:i:s', strtotime($VALUE));
                        } else {
                            $VALUE = '';
                        }
                    }
    
                    if (isset($RULES['format']['number']) && $RULES['format']['number'] === true) {
                        $VALUE = create_number($VALUE, 0);
                    }
    
                    if (isset($RULES['format']['decimals']) && !empty($RULES['format']['decimals'])) {
                        $VALUE = create_number($VALUE, $RULES['format']['decimals']);
                    }

                    if (isset($RULES['format']['json']) && $RULES['format']['json'] === true) {

                        $ARRAY = sanitizeJSON(json_decode($VALUE, true));
                        $VALUE = json_encode($ARRAY, JSON_PRETTY_PRINT);
                        
                    }

                }

                $VALUES[$name] = $VALUE;

            } elseif ($name == 'position') {

                if (isset($RULES['filter']) && !empty($RULES['filter'])) {

                    $columnName = $RULES['filter'];
                    $columnValue = isset($post[$columnName]) ? $post[$columnName] : '';

                    if ($ACTION == 'add' && !empty($columnValue)) {

                        $VALUES['position'] = sqlSelect($table, [$columnName => $columnValue, 'deleted' => 'false'])->Nrow;
                    
                    } elseif ($ACTION == 'modify' && !empty($columnValue)) {

                        $oldPosition = isset($OLD_VALUES['position']) ? $OLD_VALUES['position'] : '';
                        $columnOldValue = isset($OLD_VALUES[$columnName]) ? $OLD_VALUES[$columnName] : '';

                        if (!empty($columnOldValue) && $columnOldValue != $columnValue) {
                            
                            $VALUES['position'] = sqlSelect($table, [$columnName => $columnValue, 'deleted' => 'false'])->Nrow;

                            // ! Se la modifica non va a buon fine questi cambiamenti rimangono
                            foreach (sqlSelect($table, "`$columnName` = '$columnOldValue' AND `position` > $oldPosition AND `deleted` = 'false'") as $key => $row) {

                                $pos = $row['position'] - 1;
                                sqlModify($table, ['position' => $pos], 'id', $row['id']);

                            }

                        }

                    }

                } elseif ($ACTION == 'add') {
                    
                    $VALUES['position'] = sqlSelect($table, ['deleted' => 'false'])->Nrow;

                }

            }

        }

        return $VALUES;

    }

    function sqlExport($table, $format) {

        $ARRAY_LABEL = [];

        $LABEL = [];
        foreach (sqlSelect($table, null, 1)->row as $column => $value) {
            
            array_push($LABEL, $column);
            
        }

        array_push($ARRAY_LABEL, $LABEL);

        $ARRAY_VALUES = [];

        foreach (sqlSelect($table)->row as $key => $row) {
            
            $VALUE = [];

            foreach ($row as $column => $value) {

                array_push($VALUE, $value);

            }

            array_push($ARRAY_VALUES, $VALUE);
            
        }


        $ARRAY = array_merge($ARRAY_LABEL, $ARRAY_VALUES);
        
        if ($format == 'csv') {
            arrayToCsv($ARRAY, $table);
        }elseif ($format == 'xls') {
            arrayToXls($ARRAY, $table);
        }

    }

?>