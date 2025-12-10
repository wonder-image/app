<?php

    $TABLE->MEDIA = [
        "slug" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "link_unique" => true,
                    "lower" => true
                ]
            ]
        ],
        "name" => [],
        "alt" => [],
        "type" => [],
        "file" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "file" => true,
                    "reset" => true,
                    "max_size" => 5,
                    "max_file" => 1,
                    "name" => '{slug}',
                    "webp" => RESPONSIVE_IMAGE_WEBP,
                    "resize" => RESPONSIVE_IMAGE_SIZES
                ]
            ]
        ]
    ];

    $TABLE->LOGOS = [
        "slug" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "link_unique" => true,
                    "lower" => true
                ]
            ]
        ],
        "main" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "file" => true,
                    "reset" => true,
                    "extensions" => ['png'],
                    "max_size" => 2,
                    "max_file" => 1,
                    "name" => 'logo-{slug}',
                    "webp" => RESPONSIVE_IMAGE_WEBP,
                    "resize" => RESPONSIVE_IMAGE_SIZES
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
                    "max_size" => 2,
                    "max_file" => 1,
                    "name" => 'logo-{slug}-black',
                    "webp" => RESPONSIVE_IMAGE_WEBP,
                    "resize" => RESPONSIVE_IMAGE_SIZES
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
                    "max_size" => 2,
                    "max_file" => 1,
                    "name" => 'logo-{slug}-white',
                    "webp" => RESPONSIVE_IMAGE_WEBP,
                    "resize" => RESPONSIVE_IMAGE_SIZES
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
                    "max_size" => 2,
                    "max_file" => 1,
                    "name" => 'icon-{slug}',
                    "webp" => RESPONSIVE_IMAGE_WEBP,
                    "resize" => RESPONSIVE_IMAGE_SIZES
                ]
            ]
        ],
        "icon_black" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "file" => true,
                    "reset" => true,
                    "extensions" => ['png'],
                    "max_size" => 2,
                    "max_file" => 1,
                    "name" => 'icon-{slug}-black',
                    "webp" => RESPONSIVE_IMAGE_WEBP,
                    "resize" => RESPONSIVE_IMAGE_SIZES
                ]
            ]
        ],
        "icon_white" => [
            "input" => [
                "format" => [
                    "sanitize" => false,
                    "file" => true,
                    "reset" => true,
                    "extensions" => ['png'],
                    "max_size" => 2,
                    "max_file" => 1,
                    "name" => 'icon-{slug}-white',
                    "webp" => RESPONSIVE_IMAGE_WEBP,
                    "resize" => RESPONSIVE_IMAGE_SIZES
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
                    "max_size" => 2,
                    "max_file" => 1,
                    "name" => 'app-icon-{slug}',
                    "resize" => $DEFAULT->appIcon
                ]
            ]
        ],
    ];