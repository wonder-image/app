<?php

    $TEXT = (object) array();
    $TEXT->titleS = "colore";
    $TEXT->titleP = "colori";
    $TEXT->last = 'ultimi'; // $TEXT->last 50 $TEXT->titleP
    $TEXT->all = 'tutti'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->article = 'i'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->full = 'pieno'; // $TEXT->titleS $TEXT->full
    $TEXT->empty = 'vuoto'; // $TEXT->titleS $TEXT->empty
    $TEXT->this = 'questo'; // Sei sicuro di voler eliminare $TEXT->this $TEXT->titleS

    $NAME = (object) array();
    $NAME->table = "css-color";
    $NAME->folder = "css-color";

    $BUTTON_ADD = true;

    $FILTER_TYPE = 'limit';

    $PAGE_TABLE = $TABLE->CSS_COLOR;

    $TABLE_ACTION = [ 
        'modify' => true
    ];

    $TABLE_FIELD = [
        "var" => [
            "label" => "Var",
            "href" => "modify",
            "dimesion" => "medium",
            "phone" => false
        ],
        "name" => [
            "label" => "Nome"
        ]
    ];

    $FILTER_SEARCH = ['var', 'name'];

?>