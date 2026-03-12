<?php

    $TEXT = (object) [];
    $TEXT->titleS = "email";
    $TEXT->titleP = "email";
    $TEXT->last = 'ultime'; // $TEXT->last 50 $TEXT->titleP
    $TEXT->all = 'tutte'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->article = 'le'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->full = 'usata'; // $TEXT->titleS $TEXT->full
    $TEXT->empty = 'non usata'; // $TEXT->titleS $TEXT->empty
    $TEXT->this = 'questa'; // Sei sicuro di voler eliminare $TEXT->this $TEXT->titleS

    $NAME = (object) [];
    $NAME->table = "mail_log";
    $NAME->folder = "email";

    $PAGE_TABLE = $TABLE->MAIL_LOG;

    $BUTTON_ADD = false;

    $TABLE_FIELD = [
        "to_email" => [
            "label" => "Email",
            "href" => "view"
        ], 
        "subject" => [
            "label" => "Oggetto"
        ],
        "service" => [
            "label" => "Servizio",
            "dimension" => "little",
            "function" => [
                "name" => "mailService",
                "parameter" => "service",
                "return" => "automaticResize"
            ]
        ],
        "status" => [
            "label" => "Stato",
            "dimension" => "little",
            "function" => [
                "name" => "mailLogStatus",
                "parameter" => "status",
                "return" => "automaticResize"
            ]
        ]
    ];

    $FILTER_SEARCH = [ 'from_email', 'reply_to_email', 'to_email', 'subject' ];