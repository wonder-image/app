<?php

    $TABLE->SOCIETY = [
        "name" => [],
        "legal_name" => [],
        "email" => [],
        "pec" => [],
        "tel" => [],
        "cel" => [],
        "pi" => [],
        "cf" => [],
        "sdi" => [
            "input" => [
                "format" => [
                    "upper" => true
                ]
            ]
        ],
        "rea" => [],
        "share_capital" => [
            "input" => [
                "format" => [
                    "decimals" => 2
                ]
            ]
        ]
    ];

    $TABLE->SOCIETY_ADDRESS = [
        "country" => [],
        "province" => [],
        "city" => [],
        "cap" => [
            "sql" => [
                "type" => "int",
                "length" => 5
            ],
            "input" => [
                "format" => [
                    "number" => true
                ]
            ]
        ],
        "street" => [],
        "number" => [],
        "more" => [],
        "gmaps" => [],
        "timetable" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                ]
            ]
        ],
    ];

    $TABLE->SOCIETY_LEGAL_ADDRESS = [
        "legal_country" => [],
        "legal_province" => [],
        "legal_city" => [],
        "legal_cap" => [
            "sql" => [
                "type" => "int",
                "length" => 5
            ],
            "input" => [
                "format" => [
                    "number" => true
                ]
            ]
        ],
        "legal_street" => [],
        "legal_number" => [],
        "legal_more" => [],
        "legal_gmaps" => []
    ];

    $TABLE->SOCIETY_SOCIAL = [
        "site" => [
            "input" => [
                "label" => "Sito"
            ]
        ],
        "instagram" => [
            "input" => [
                "label" => "Instagram"
            ]
        ],
        "facebook" => [
            "input" => [
                "label" => "Facebook"
            ]
        ],
        "tiktok" => [
            "input" => [
                "label" => "TikTok"
            ]
        ],
        "linkedin" => [
            "input" => [
                "label" => "Linkedin"
            ]
        ],
        "whatsapp" => [
            "input" => [
                "label" => "WhatsApp"
            ]
        ],
        "youtube" => [
            "input" => [
                "label" => "Youtube"
            ]
        ]
    ];

    $APP_ICON_RESIZE = [];
    foreach ($DEFAULT->appIcon as $size) { 
        array_push($APP_ICON_RESIZE, [ 
            'width' => $size,
            'height' => $size
        ]);
    }

    $TABLE->LOGOS = [
        "main" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "file" => true,
                    "reset" => true,
                    "extensions" => ['png'],
                    "max_size" => 1,
                    "max_file" => 1,
                    "dir" => '/Logo'
                ]
            ]
        ],
        "black" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "file" => true,
                    "reset" => true,
                    "extensions" => ['png'],
                    "max_size" => 1,
                    "max_file" => 1,
                    "dir" => '/Logo-Black'
                ]
            ]
        ],
        "white" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "file" => true,
                    "reset" => true,
                    "extensions" => ['png'],
                    "max_size" => 1,
                    "max_file" => 1,
                    "dir" => '/Logo-White'
                ]
            ]
        ],
        "icon" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "file" => true,
                    "reset" => true,
                    "extensions" => ['png'],
                    "max_size" => 1,
                    "max_file" => 1,
                    "dir" => '/Logo-Icon'
                ]
            ]
        ],
        "favicon" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "file" => true,
                    "reset" => true,
                    "extensions" => ['ico'],
                    "max_size" => 1,
                    "max_file" => 1,
                    "dir" => '/../../../favicon'
                ]
            ]
        ],
        "app_icon" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "file" => true,
                    "reset" => true,
                    "extensions" => ['png'],
                    "max_size" => 1,
                    "max_file" => 1,
                    "dir" => '/App-Icon',
                    "resize" => $APP_ICON_RESIZE
                ]
            ]
        ],
    ];