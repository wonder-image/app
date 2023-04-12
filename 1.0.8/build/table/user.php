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
        "password" => [],
        "authority" => [],
        "area" => [],
        "active" => []
    ];

?>