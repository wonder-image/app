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
                    "extensions" => ['png'],
                    "max_size" => 1,
                    "max_file" => 1,
                    "dir" => '../user/profile-picture/',
                    "reset" => true
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