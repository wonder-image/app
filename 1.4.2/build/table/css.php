<?php

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

    $TABLE->CSS_DEFAULT = [
        "font_id" => [
            "sql" => [
                "type" => "INT",
                "foreign_table" => "css_font"
            ]
        ],
        "font_weight" => [],
        "font_size" => [],
        "line_height" => [],
        "title_big_font_id" => [
            "sql" => [
                "type" => "INT",
                "foreign_table" => "css_font"
            ]
        ],
        "title_big_font_weight" => [],
        "title_big_font_size" => [],
        "title_big_line_height" => [],
        "title_font_id" => [
            "sql" => [
                "type" => "INT",
                "foreign_table" => "css_font"
            ]
        ],
        "title_font_weight" => [],
        "title_font_size" => [],
        "title_line_height" => [],
        "subtitle_font_id" => [],
        "subtitle_font_weight" => [],
        "subtitle_font_size" => [],
        "subtitle_line_height" => [],
        "text_font_id" => [
            "sql" => [
                "type" => "INT",
                "foreign_table" => "css_font"
            ]
        ],
        "text_font_weight" => [],
        "text_font_size" => [],
        "text_line_height" => [],
        "text_small_font_id" => [
            "sql" => [
                "type" => "INT",
                "foreign_table" => "css_font"
            ]
        ],
        "text_small_font_weight" => [],
        "text_small_font_size" => [],
        "text_small_line_height" => [],
        "button_font_size" => [],
        "button_line_height" => [],
        "button_font_weight" => [],
        "button_border_radius" => [],
        "button_border_width" => [],
        "badge_font_size" => [],
        "badge_line_height" => [],
        "badge_font_weight" => [],
        "badge_border_radius" => [],
        "badge_border_width" => [],
        "tx_color" => [],
        "bg_color" => [],
        "spacer" => [],
        "header_height" => [
            "sql" => [
                "default" => "80"
            ]
        ]
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
        "color" => [],
        "contrast" => []
    ];

    $TABLE->CSS_INPUT = [
        "tx_color" => [],
        "bg_color" => [],
        "disabled_bg_color" => [],
        "label_color" => [],
        "label_color_focus" => [],
        "label_weight" => [],
        "label_weight_focus" => [],
        "select_hover" => [],
        "border_color" => [],
        "border_color_focus" => [],
        "border_radius" => [],
        "border_top" => [],
        "border_right" => [],
        "border_bottom" => [],
        "border_left" => [],
        "date_default" => [],
        "date_active" => [],
        "date_bg" => [],
        "date_bg_hover" => [],
        "date_border_radius" => []
    ];

    $TABLE->CSS_MODAL = [
        "tx" => [],
        "bg" => [],
        "border_color" => [],
        "border_width" => [],
        "border_radius" => []
    ];

    $TABLE->CSS_DROPDOWN = [
        "tx" => [],
        "bg" => [],
        "bg_hover" => [],
        "border_color" => [],
        "border_width" => [],
        "border_radius" => []
    ];

    $TABLE->CSS_ALERT = [
        "tx" => [],
        "bg" => [],
        "top" => [],
        "right" => [],
        "border_color" => [],
        "border_width" => [],
        "border_radius" => []
    ];