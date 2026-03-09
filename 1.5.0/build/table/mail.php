<?php

    $TABLE->MAIL_LOG = [
        "user_id" => [
            "sql" => [
                "type" => "INT",
                "foreign_table" => "user"
            ]
        ],
        "from_email" => [
            "sql" => [
                "length" => 255
            ]
        ],
        "reply_to_email" => [
            "sql" => [
                "length" => 255
            ]
        ],
        "to_email" => [
            "sql" => [
                "length" => 255
            ]
        ],
        "subject" => [
            "sql" => [
                "length" => 1000
            ]
        ],
        "template" => [
            "sql" => [
                "length" => 100
            ]
        ],
        "body_raw" => [
            "sql" => [
                "type" => "LONGTEXT"
            ]
        ],
        "body_text" => [
            "sql" => [
                "type" => "LONGTEXT"
            ],
            "input" => [
                "format" => [
                    "html_to_text" => true
                ]
            ]
        ],
        "attachments" => [
            "sql" => [
                "type" => "JSON"
            ]
        ],
        "status" => [
            "sql" => [
                "length" => 20
            ]
        ],
        "error_message" => [
            "sql" => [
                "type" => "LONGTEXT"
            ]
        ],
        "request_uri" => [],
        "ip" => [
            "sql" => [
                "length" => 45
            ]
        ],
        "user_agent" => [
            "sql" => [
                "length" => 255
            ]
        ],
        "ind_status" => [
            "sql" => [
                "index" => "status"
            ]
        ],
        "ind_user_id" => [
            "sql" => [
                "index" => "user_id"
            ]
        ],
        "ind_to_status" => [
            "sql" => [
                "index" => [ "to_email", "status" ]
            ]
        ]
    ];
