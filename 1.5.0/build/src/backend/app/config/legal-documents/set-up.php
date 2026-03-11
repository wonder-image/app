<?php

    $TEXT = (object) array();
    $TEXT->titleS = "documento legale";
    $TEXT->titleP = "documenti legali";
    $TEXT->last = 'ultimi';
    $TEXT->all = 'tutti';
    $TEXT->article = 'i';
    $TEXT->full = 'pieno';
    $TEXT->empty = 'vuoto';
    $TEXT->this = 'questo';

    $NAME = (object) array();
    $NAME->table = "legal_documents";
    $NAME->folder = "legal-documents";

    $BUTTON_ADD = true;
    $FILTER_TYPE = 'limit';
    $FILTER_ORDER = 'published_at';
    $FILTER_DIRECTION = 'DESC';

    $PAGE_TABLE = $TABLE->LEGAL_DOCUMENTS;

    $TABLE_ACTION = [
        'duplicate' => true
    ];

    $TABLE_FIELD = [
        "doc_type" => [
            "label" => "Tipologia",
            "href" => "modify"
        ],
        "language_code" => [
            "label" => "Lingua",
            "dimension" => "little"
        ],
        "version" => [
            "label" => "Versione",
            "dimension" => "little"
        ],
        "checkbox_label" => [
            "label" => "Testo checkbox"
        ],
        "active" => [
            "label" => "Attivo",
            "dimension" => "little",
            "function" => [
                "name" => "active",
                "return" => "automaticResize"
            ]
        ],
        "published_at" => [
            "label" => "Pubblicato",
            "format" => "datetime",
            "dimension" => "little"
        ]
    ];

    $FILTER_SEARCH = [ 'doc_type', 'checkbox_label', 'version', 'language_code', 'content_hash' ];

    $FILTER_CUSTOM = [
        "active" => [
            'database' => false,
            'column' => 'active',
            'name' => 'Stato',
            'search' => false,
            'type' => 'radio',
            'array' => [
                '' => 'Tutti',
                'true' => 'Attivi',
                'false' => 'Non attivi'
            ]
        ],
        "language_code" => [
            'database' => false,
            'column' => 'language_code',
            'name' => 'Lingua',
            'search' => false,
            'type' => 'radio',
            'array' => [ '' => 'Tutte', ...array_map(fn($lang) => $lang['name'], __ls()) ]
        ]
    ];
