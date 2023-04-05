<?php

    $TABLE->CSS_DEFAULT = [
        "font_id" => [],
        "font_size" => [],
        "font_weight" => [],
        "spacer" => []
    ];

    $TABLE->CSS_FONT = [
        "name" => [
            "input" => [
                "format" => [
                    "unique" => true
                ]
            ]
        ],
        "link" => [],
        "font_family" => [],
        "visible" => []
    ];

    $TABLE->CSS_COLOR = [
        "var" => [
            "input" => [
                "format" => [
                    "unique" => true
                ]
            ]
        ],
        "name" => [],
        "color" => []
    ];

?>