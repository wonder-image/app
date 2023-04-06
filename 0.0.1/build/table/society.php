<?php

    $TABLE->SOCIETY = [
        "name" => [],
        "legal_name" => [],
        "email" => [],
        "pec" => [],
        "tel" => [
            "input" => [
                "format" => [
                    "number" => true
                ]
            ]
        ],
        "cel" => [
            "input" => [
                "format" => [
                    "number" => true
                ]
            ]
        ],
        "pi" => [
            "input" => [
                "format" => [
                    "number" => true
                ]
            ]
        ],
        "cf" => [],
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
                "lenght" => 5
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
        "gmaps" => []
    ];

    $TABLE->SOCIETY_LEGAL_ADDRESS = [
        "legal_country" => [],
        "legal_province" => [],
        "legal_city" => [],
        "legal_cap" => [
            "sql" => [
                "type" => "int",
                "lenght" => 5
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
        ]
    ];

    $TABLE->LOGOS = [
        "main" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "file" => true,
                    "extensions" => ['png'],
                    "max_size" => 1,
                    "max_file" => 1
                ]
            ]
        ],
        "black" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "file" => true,
                    "extensions" => ['png'],
                    "max_size" => 1,
                    "max_file" => 1
                ]
            ]
        ],
        "white" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "file" => true,
                    "extensions" => ['png'],
                    "max_size" => 1,
                    "max_file" => 1
                ]
            ]
        ],
        "icon" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "file" => true,
                    "extensions" => ['png'],
                    "max_size" => 1,
                    "max_file" => 1
                ]
            ]
        ],
        "favicon" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "file" => true,
                    "extensions" => ['ico'],
                    "max_size" => 1,
                    "max_file" => 1,
                    "dir" => '/../../../../favicon'
                ]
            ]
        ]
    ];
    
?>