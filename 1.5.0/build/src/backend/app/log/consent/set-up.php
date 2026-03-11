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

    $NAME->table = "consent_events";
    $NAME->folder = "consent";

    $PAGE_TABLE = $TABLE->CONSENT_EVENTS;

    $BUTTON_ADD = false;

    $TABLE_FIELD = [
        "user_id" => [
            "format" => "user_name",
            "label" => "Utente",
            "href" => "view"
        ], 
        "consent_type" => [
            "label" => "Consenso"
        ],
        "action" => [
            "label" => "Azione",
            "function" => [
                "name" => "consentEventAction",
                "parameter" => "action",
                "return" => "automaticResize"
            ]
        ]
    ];