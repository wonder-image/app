<?php

    require_once "../set-up.php";

    $TEXT = (object) [];
    $TEXT->titleS = "documento";
    $TEXT->titleP = "documenti";
    $TEXT->last = 'ultimi'; // $TEXT->last 50 $TEXT->titleP
    $TEXT->all = 'tutti'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->article = 'i'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->full = 'usato'; // $TEXT->titleS $TEXT->full
    $TEXT->empty = 'non usato'; // $TEXT->titleS $TEXT->empty
    $TEXT->this = 'questo'; // Sei sicuro di voler eliminare $TEXT->this $TEXT->titleS

    $NAME->folder = "documents";
    
    $QUERY_CUSTOM = "type = 'document'";

    $TABLE_FIELD = [
        "name" => [
            "label" => "File",
            "href" => "modify"
        ], 
        "slug" => [
            "label" => "Slug"
        ]
    ];
