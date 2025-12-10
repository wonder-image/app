<?php

    require_once "../set-up.php";

    $TEXT = (object) [];
    $TEXT->titleS = "immagine";
    $TEXT->titleP = "immagini";
    $TEXT->last = 'ultime'; // $TEXT->last 50 $TEXT->titleP
    $TEXT->all = 'tutte'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->article = 'le'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->full = 'usata'; // $TEXT->titleS $TEXT->full
    $TEXT->empty = 'non usata'; // $TEXT->titleS $TEXT->empty
    $TEXT->this = 'questa'; // Sei sicuro di voler eliminare $TEXT->this $TEXT->titleS

    $NAME->folder = "images";
    
    $QUERY_CUSTOM = "type = 'image'";

    $TABLE_FIELD = [
        "file" => [
            "format" => "image",
            "label" => "Immagine",
            "href" => "modify"
        ],
        "name" => [
            "label" => "File",
            "href" => "modify"
        ], 
        "slug" => [
            "label" => "Slug"
        ]
    ];
