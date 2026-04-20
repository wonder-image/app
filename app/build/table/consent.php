<?php

    /**
     * Event store dei consensi: append-only, mai sovrascrivere.
     */
    $TABLE->CONSENT_EVENTS = [
        '__table' => [
            'sql' => [
                'audit_columns' => true,
                'audit_auto_columns' => false
            ]
        ],
        'user_id' => [
            'sql' => [
                'type' => 'INT',
                'null' => false,
                'foreign_table' => 'user'
            ]
        ],
        'consent_type' => [
            'sql' => [
                'type' => 'VARCHAR',
                'length' => 120,
                'null' => false
            ]
        ],
        'action' => [
            'sql' => [
                'type' => 'ENUM',
                'enum' => [ 'accept', 'reject', 'withdraw' ],
                'null' => false
            ]
        ],
        'legal_document_id' => [
            'sql' => [
                'type' => 'INT',
                'null' => true,
                'foreign_table' => 'legal_documents'
            ]
        ],
        'occurred_at' => [
            'sql' => [
                'type' => 'DATETIME',
                'null' => false
            ]
        ],
        'ip_address' => [
            'sql' => [
                'length' => 45,
                'null' => false
            ]
        ],
        'user_agent' => [
            'sql' => [
                'length' => 1000,
                'null' => false
            ]
        ],
        'locale' => [
            'sql' => [
                'length' => 2,
                'null' => false
            ]
        ],
        'source' => [
            'sql' => [
                'type' => 'ENUM',
                'enum' => [ 'web', 'app', 'api', 'admin' ],
                'null' => false
            ]
        ],
        'ui_surface' => [
            'sql' => [
                'length' => 120,
                'null' => false
            ]
        ],
        'evidence_json' => [
            'sql' => [
                'type' => 'JSON',
                'null' => true
            ],
            'input' => [
                'format' => [
                    'sanitize' => false,
                    'json' => true
                ]
            ]
        ],
        'creation' => [
            'sql' => [
                'type' => 'DATETIME',
                'null' => false
            ]
        ],
        'idx_user_consent_type_time' => [
            'sql' => [
                'index' => [ 'user_id', 'consent_type', 'occurred_at' ]
            ]
        ],
        'idx_legal_document_id' => [
            'sql' => [
                'index' => 'legal_document_id'
            ]
        ]
    ];

    /**
     * Stato corrente dei consensi derivato dagli eventi.
     */
    $TABLE->USER_CONSENT_STATE = [
        '__table' => [
            'sql' => [
                'auto_id' => false,
                'audit_columns' => false
            ]
        ],
        'user_id' => [
            'sql' => [
                'type' => 'INT',
                'null' => false,
                'foreign_table' => 'user'
            ]
        ],
        'consent_type' => [
            'sql' => [
                'type' => 'VARCHAR',
                'length' => 120,
                'null' => false
            ]
        ],
        'current_status' => [
            'sql' => [
                'type' => 'ENUM',
                'enum' => [ 'accepted', 'rejected', 'withdrawn', 'pending' ],
                'null' => false
            ]
        ],
        'legal_document_id' => [
            'sql' => [
                'type' => 'INT',
                'null' => true,
                'foreign_table' => 'legal_documents'
            ]
        ],
        'last_event_id' => [
            'sql' => [
                'type' => 'INT',
                'null' => false,
                'foreign_table' => 'consent_events'
            ]
        ],
        'updated_at' => [
            'sql' => [
                'type' => 'DATETIME',
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'on_update' => 'CURRENT_TIMESTAMP'
            ]
        ],
        'pk_user_consent_type' => [
            'sql' => [
                'primary' => [ 'user_id', 'consent_type' ]
            ]
        ],
        'idx_consent_type_status' => [
            'sql' => [
                'index' => [ 'consent_type', 'current_status' ]
            ]
        ]
    ];

    /**
     * Token di conferma.
     */
    $TABLE->CONSENT_CONFIRMATION_TOKENS = [
        '__table' => [
            'sql' => [
                'audit_columns' => false
            ]
        ],
        'token_type' => [
            'sql' => [
                'length' => 50,
                'null' => false
            ]
        ],
        'user_id' => [
            'sql' => [
                'type' => 'INT',
                'null' => false,
                'foreign_table' => 'user'
            ]
        ],
        'token' => [
            'sql' => [
                'length' => 128,
                'null' => false,
                'unique' => true
            ]
        ],
        'language_code' => [
            'sql' => [
                'length' => 2,
                'null' => true
            ]
        ],
        'continue_url' => [
            'sql' => [
                'type' => 'LONGTEXT',
                'null' => true
            ]
        ],
        'metadata_json' => [
            'sql' => [
                'type' => 'JSON',
                'null' => true
            ],
            'input' => [
                'format' => [
                    'sanitize' => false,
                    'json' => true
                ]
            ]
        ],
        'expires_at' => [
            'sql' => [
                'type' => 'DATETIME',
                'null' => false
            ]
        ],
        'confirmed_at' => [
            'sql' => [
                'type' => 'DATETIME',
                'null' => true
            ]
        ],
        'revoked_at' => [
            'sql' => [
                'type' => 'DATETIME',
                'null' => true
            ]
        ],
        'created_at' => [
            'sql' => [
                'type' => 'DATETIME',
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP'
            ]
        ],
        'idx_user_expires' => [
            'sql' => [
                'index' => [ 'user_id', 'token_type', 'expires_at' ]
            ]
        ]
    ];
