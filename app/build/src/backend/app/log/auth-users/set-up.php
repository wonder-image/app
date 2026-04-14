<?php

    $TEXT = (object) [];
    $TEXT->titleS = "accesso utenti";
    $TEXT->titleP = "accessi utente";
    $TEXT->last = 'ultimi'; // $TEXT->last 50 $TEXT->titleP
    $TEXT->all = 'tutti'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->article = 'gli'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->full = 'usato'; // $TEXT->titleS $TEXT->full
    $TEXT->empty = 'non usato'; // $TEXT->titleS $TEXT->empty
    $TEXT->this = 'questo'; // Sei sicuro di voler eliminare $TEXT->this $TEXT->titleS

    $NAME = (object) [];
    $NAME->table = "auth_log";
    $NAME->folder = "auth-users";

    $PAGE_TABLE = $TABLE->AUTH_LOG;

    $BUTTON_ADD = false;

    $TABLE_FIELD = [
        "user_id" => [
            "format" => "user_name",
            "label" => "Utente",
            "href" => "view"
        ], 
        "ip" => [
            "label" => "Ip"
        ],
        "event" => [
            "label" => "Evento"
        ], 
        "area" => [
            "label" => "Area"
        ], 
        "success" => [
            "label" => "Success",
            "format" => "status"
        ]
    ];
