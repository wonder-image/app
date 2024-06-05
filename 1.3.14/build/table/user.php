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
        "authority" => [],
        "area" => [],
        "active" => [
            "sql" => [
                "default" => "true"
            ]
        ]
    ];

?>