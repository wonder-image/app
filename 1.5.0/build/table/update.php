<?php

    $TABLE->APP_UPDATE_RUNS = [
        'release_id' => [
            'sql' => [
                'length' => 100,
                'null' => true
            ]
        ],
        'trigger_type' => [
            'sql' => [
                'length' => 50
            ]
        ],
        'status' => [
            'sql' => [
                'length' => 30
            ]
        ],
        'request_ip' => [
            'sql' => [
                'length' => 45,
                'null' => true
            ]
        ],
        'request_uri' => [
            'sql' => [
                'type' => 'TEXT',
                'null' => true
            ]
        ],
        'app_version' => [
            'sql' => [
                'length' => 20
            ]
        ],
        'runner_version' => [
            'sql' => [
                'length' => 20
            ]
        ],
        'source' => [
            'sql' => [
                'length' => 30
            ]
        ],
        'started_at' => [
            'sql' => [
                'type' => 'DATETIME'
            ]
        ],
        'finished_at' => [
            'sql' => [
                'type' => 'DATETIME',
                'null' => true
            ]
        ],
        'payload_json' => [
            'sql' => [
                'type' => 'LONGTEXT',
                'null' => true
            ]
        ],
        'error_message' => [
            'sql' => [
                'type' => 'TEXT',
                'null' => true
            ]
        ],
        'ind_release_status' => [
            'sql' => [
                'index' => [ 'release_id', 'status' ]
            ]
        ]
    ];
