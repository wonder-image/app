<?php

    $TEXT = (object) array();
    $TEXT->titleS = "errore";
    $TEXT->titleP = "errori";
    $TEXT->last = 'ultimi'; // $TEXT->last 50 $TEXT->titleP
    $TEXT->all = 'tutti'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->article = 'gli'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->full = 'pieno'; // $TEXT->titleS $TEXT->full
    $TEXT->empty = 'vuoto'; // $TEXT->titleS $TEXT->empty
    $TEXT->this = 'questo'; // Sei sicuro di voler eliminare $TEXT->this $TEXT->titleS

    $NAME = (object) array();
    $NAME->table = "sql_error";
    $NAME->folder = "error";

    $QUERY_CUSTOM = "";

    $FILTER_TYPE = 'limit';

    $BUTTON_ADD = false;

    $ARRAY_TABLE = [];
    $ARRAY_ERROR_N = [];

    foreach (sqlSelect($NAME->table, ['deleted' => 'false'])->row as $key => $row) {
        
        $table = $row['table'];
        $error_n = $row['error_n'];

        if (!in_array($table, $ARRAY_TABLE)) { $ARRAY_TABLE[$table] = $table; }
        if (!in_array($error_n, $ARRAY_ERROR_N)) { $ARRAY_ERROR_N[$error_n] = $error_n; }

    }

    $FILTER_CUSTOM = [
        "table" => [
            'array' => $ARRAY_TABLE,
            'column' => 'table',
            'name' => 'Tabella',
            'search' => true,
            'type' => 'checkbox'
        ],
        "error_n" => [
            'array' => $ARRAY_ERROR_N,
            'column' => 'error_n',
            'name' => 'Errore N°',
            'search' => true,
            'type' => 'checkbox'
        ],
    ];

    $PAGE_TABLE = $TABLE->SQL_ERROR;

    $TABLE_ACTION = [
        'view' => true
    ];

    $TABLE_FIELD = [
        "table" => [
            "label" => "Tabella",
            "href" => "view"
        ],
        "error_n" => [
            "label" => "N°"
        ],
        "error" => [
            "label" => "Errore"
        ],
    ];

    $FILTER_SEARCH = ['table', 'error_n', 'error'];

?>