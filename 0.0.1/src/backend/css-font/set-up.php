<?php

    $TEXT = (object) array();
    $TEXT->titleS = "font";
    $TEXT->titleP = "font";
    $TEXT->last = 'ultimi'; // $TEXT->last 50 $TEXT->titleP
    $TEXT->all = 'tutti'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->article = 'i'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->full = 'pieno'; // $TEXT->titleS $TEXT->full
    $TEXT->empty = 'vuoto'; // $TEXT->titleS $TEXT->empty
    $TEXT->this = 'questo'; // Sei sicuro di voler eliminare $TEXT->this $TEXT->titleS

    $NAME = (object) array();
    $NAME->table = "css_font";
    $NAME->folder = "css-font";

    $BUTTON_ADD = true;

    $FILTER_TYPE = 'limit';

    $PAGE_TABLE = $TABLE->CSS_FONT;

    $TABLE_ACTION = [ 
        'modify' => true,
        'visible' => true
    ];

    $TABLE_FIELD = [
        "name" => [
            "label" => "Nome",
            "href" => "modify",
        ],
        "visible" => [
            "function" => [
                "name" => "visible",
                "return" => "automaticResize"
            ]
        ]
    ];

    $FILTER_SEARCH = ['var', 'name'];

?>