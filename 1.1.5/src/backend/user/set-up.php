<?php

    $TEXT = (object) array();
    $TEXT->titleS = "utente";
    $TEXT->titleP = "utenti";
    $TEXT->last = 'ultimi'; // $TEXT->last 50 $TEXT->titleP
    $TEXT->all = 'tutti'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->article = 'gli'; // Lista $TEXT->all $TEXT->article $titlePageP
    $TEXT->full = 'pieno'; // $TEXT->titleS $TEXT->full
    $TEXT->empty = 'vuoto'; // $TEXT->titleS $TEXT->empty
    $TEXT->this = 'questo'; // Sei sicuro di voler eliminare $TEXT->this $TEXT->titleS

    $NAME = (object) array();
    $NAME->table = "user";
    $NAME->folder = "user";

    $USER_FILTER = (object) array();
    $USER_FILTER->area = "backend";
    $USER_FILTER->authority = "";

    $FILTER_TYPE = 'limit';

    $BUTTON_ADD = true;

    $FILTER_CUSTOM = [
        "authority" => [
            'database' => false,
            'function' => 'permissionsBackend',
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
                "name" => "permissionsBackend",
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

    $FILTER_SEARCH = ['username', 'name', 'surname', 'email'];

?>