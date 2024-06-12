<?php

    function sqlTable($TABLE, $COLUMN, $ENGINE = "MyISAM", $CHARSET = "latin1") {

        global $mysqli;
        global $MYSQLI_CONNECTION;

        if (isset($COLUMN['DATABASE'])) {

            $connection = $MYSQLI_CONNECTION[$COLUMN['DATABASE']];
            unset($COLUMN['DATABASE']);

        } else {

            $connection = $mysqli;

        }

        $SQL = new Wonder\Sql\CreateTable($connection);
        $SQL->ENGINE = $ENGINE;
        $SQL->CHARSET = $CHARSET;

        return $SQL->Table( $TABLE, $COLUMN );

    }

    function sqlInsert( $table, $values ) {

        global $mysqli;

        $SQL = new Wonder\Sql\Query($mysqli);

        return $SQL->Insert( $table, $values );

    }

    function sqlModify( $table, $values, $column, $value ) {

        global $mysqli;

        $SQL = new Wonder\Sql\Query($mysqli);

        return $SQL->Update( $table, $values, $column, $value );

    }

    function sqlSelect($table, $condition = null, $limit = null, $order = null, $orderDirection = null, $attributes = '*') {

        global $mysqli;

        $SQL = new Wonder\Sql\Query($mysqli);

        return $SQL->Select( $table, $condition, $limit, $order, $orderDirection, $attributes );

    }

    function sqlDelete($table, $condition = null) {
        
        global $mysqli;

        $SQL = new Wonder\Sql\Query($mysqli);

        return $SQL->Delete( $table, $condition );

    }

    function sqlTruncate($table) {
        
        global $mysqli;

        $SQL = new Wonder\Sql\Query($mysqli);

        return $SQL->Truncate( $table );

    }
    
    function sqlSum($table, $query = null, $column = '*') {

        global $mysqli;

        $SQL = new Wonder\Sql\Query($mysqli);

        return $SQL->Sum( $table, $query, $column );

    }

    function sqlCount($table, $query = null, $column = '*', $distinct = false) {

        global $mysqli;

        $SQL = new Wonder\Sql\Query($mysqli);

        return $SQL->Count( $table, $query, $column, $distinct );

    }

    function sqlDatabase($database = 'main') {

        global $mysqli;
        global $MYSQLI_CONNECTION;

        $mysqli = $MYSQLI_CONNECTION[$database];

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

    function sqlDatabaseExists($database) {

        global $mysqli;

        $SQL = new Wonder\Sql\Query($mysqli);

        return $SQL->DatabaseExists( $database );

    }

    function sqlTableExists($table) {

        global $mysqli;

        $SQL = new Wonder\Sql\Query($mysqli);

        return $SQL->TableExists( $table );

    }

    function sqlColumnExists($table, $column) {

        global $mysqli;

        $SQL = new Wonder\Sql\Query($mysqli);

        return $SQL->ColumnExists( $table, $column );
        
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
        } elseif ($format == 'xls') {
            arrayToXls($ARRAY, $table);
        }

    }