<?php

    $TEXT = (object) [];
    $TEXT->titleS = "utente API";
    $TEXT->titleP = "utenti API";
    $TEXT->last = 'ultimi'; // $TEXT->last 50 $TEXT->titleP
    $TEXT->all = 'tutti'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->article = 'gli'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->full = 'pieno'; // $TEXT->titleS $TEXT->full
    $TEXT->empty = 'vuoto'; // $TEXT->titleS $TEXT->empty
    $TEXT->this = 'questo'; // Sei sicuro di voler eliminare $TEXT->this $TEXT->titleS

    $NAME = (object) [];
    $NAME->table = "user";
    $NAME->folder = "user";

    $USER_FILTER = (object) [];
    $USER_FILTER->area = "api";
    $USER_FILTER->authority = "";

    $FILTER_TYPE = 'limit';

    $BUTTON_ADD = true;

    $FILTER_CUSTOM = [
        "authority" => [
            'database' => false,
            'function' => 'permissionsApi',
            'column' => 'authority',
            'column_type' => 'multiple',
            'name' => 'Autorizzazione',
            'search' => false,
            'type' => 'radio'
        ],
        "active" => [
            'database' => false,
            'column' => 'active',
            'name' => 'Stato',
            'search' => false,
            'type' => 'radio'
        ]
    ];

    $PAGE_TABLE = $TABLE->USER;

    $TABLE_ACTION = [
        'view' => true,
        'modify' => true,
        'active' => true,
        'authority' => true
    ];

    $TABLE_FIELD = [
        "username" => [
            "label" => "Username",
            "href" => "modify"
        ],
        "name" => [
            "label" => "Nome",
            "value" => ['name', 'surname'],
            "phone" => false
        ],
        "email" => [
            "label" => "Email",
            "href" => "mailto",
            "tablet" => false
        ],
        "authority" => [
            "function" => [
                "name" => "permissionsApi",
                "return" => "automaticResize"
            ]
        ],
        "active" => [
            "function" => [
                "name" => "active",
                "return" => "automaticResize"
            ]
        ]
    ];

    $FILTER_SEARCH = [ 'username', 'name', 'surname', 'email' ];