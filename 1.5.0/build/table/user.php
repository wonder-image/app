<?php

    $TABLE->USER = [
        "name" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "sanitizeFirst" => true
                ]
            ]
        ],
        "surname" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "sanitizeFirst" => true
                ]
            ]
        ],
        "email" => [
            "input" => [
                "format" => [
                    "unique" => true
                ]
            ]
        ],
        "phone" => [
            "input" => [
                "format" => [
                    "unique" => true
                ]
            ]
        ],
        "username" => [
            "input" => [
                "format" => [
                    "unique" => true
                ]
            ]
        ],
        "profile_picture" => [
            "sql" => [
                "type" => "JSON"
            ],
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "file" => true,
                    "extensions" => ['png', 'jpg', 'jpeg'],
                    "max_size" => 1,
                    "max_file" => 1,
                    "dir" => '/profile-picture/',
                    "reset" => true,
                    "resize" => [
                        [
                            "width" => 960,
                            "height" => 960
                        ],[
                            "width" => 480,
                            "height" => 480
                        ],[
                            "width" => 240,
                            "height" => 240
                        ],[
                            "width" => 120,
                            "height" => 120
                        ]
                    ]
                ]
            ]
        ],
        "color" => [],
        "password" => [],
        "authority" => [
            "sql" => [
                "type" => "JSON"
            ],
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "json" => true
                ]
            ]
        ],
        "area" => [
            "sql" => [
                "type" => "JSON"
            ],
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "json" => true
                ]
            ]
        ],
        "active" => [
            "sql" => [
                "default" => "true"
            ]
        ]
    ];

    $TABLE->AUTH_REMEMBER = [
        "user_id" => [
            "sql" => [
                "type" => "INT",
                "foreign_table" => "user"
            ]
        ],
        "selector" => [
            "sql" => [
                "length" => 64
            ]
        ],
        "token_hash" => [
            "sql" => [
                "length" => 128
            ]
        ],
        "area" => [
            "sql" => [
                "length" => 20
            ]
        ],
        "expires_at" => [
            "sql" => [
                "type" => "DATETIME"
            ]
        ],
        "last_used" => [
            "sql" => [
                "type" => "DATETIME"
            ]
        ],
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
        "ind_selector" => [
            "sql" => [
                "index" => "selector"
            ]
        ]
    ];


    $TABLE->API_USERS = [
        "user_id" => [
            "sql" => [
                "type" => "INT",
                "foreign_table" => "user"
            ]
        ],
        "allowed_domains" => [
            "sql" => [
                "type" => "JSON"
            ],
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "json" => true
                ]
            ]
        ],
        "allowed_ips" => [
            "sql" => [
                "type" => "JSON"
            ],
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "json" => true
                ]
            ]
        ],
        "token" => [
            "sql" => [
                "length" => 512
            ],
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "unique" => true
                ]
            ]
        ],
        "active" => [
            "sql" => [
                "default" => "true"
            ]
        ]
    ];

    $TABLE->API_ACTIVITY = [
        "user_id" => [
            "sql" => [
                "type" => "INT",
                "foreign_table" => "user"
            ]
        ],
        "token_id" => [
            "sql" => [
                "type" => "INT",
                "foreign_table" => "api_users"
            ]
        ],
        "token" => [
            "sql" => [
                "length" => 512
            ],
            "input" => [
                "format" => [
                    "sanitize" => false
                ]
            ]
        ],
        "ip" => [
            "sql" => [
                "length" => 24
            ]
        ],
        "domain" => [
            "sql" => [
                "length" => 100
            ],
            "input" => [
                "format" => [
                    "sanitize" => false
                ]
            ]
        ],
        "version" => [
            "sql" => [
                "length" => 11
            ]
        ],
        "endpoint" => [],
        "request_method" => [
            "sql" => [
                "length" => 5
            ]
        ],
        "content_type" => [
            "sql" => [
                "length" => 100
            ]
        ],
        "parameters" => [
            "sql" => [
                "type" => "JSON"
            ],
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "json" => true
                ]
            ]
        ],
        "data" => [
            "sql" => [
                "type" => "JSON"
            ],
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "json" => true
                ]
            ]
        ],
        "files" => [
            "sql" => [
                "type" => "JSON"
            ],
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "json" => true
                ]
            ]
        ],
        "success" => [
            "sql" => [
                "type" => "BOOLEAN"
            ]
        ],
        "status" => [
            "sql" => [
                "type" => "INT"
            ]
        ],
        "response" => [
            "sql" => [
                "type" => "JSON"
            ],
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "json" => true
                ]
            ]
        ]
    ];
