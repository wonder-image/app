<?php

    $TEXT = (object) [];
    $TEXT->titleS = "accessi utenti";
    $TEXT->titleP = "accesso utente";
    $TEXT->last = 'ultimi'; // $TEXT->last 50 $TEXT->titleP
    $TEXT->all = 'tutti'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->article = 'gli'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->full = 'usato'; // $TEXT->titleS $TEXT->full
    $TEXT->empty = 'non usato'; // $TEXT->titleS $TEXT->empty
    $TEXT->this = 'questo'; // Sei sicuro di voler eliminare $TEXT->this $TEXT->titleS

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