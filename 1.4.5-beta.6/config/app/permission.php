<?php

    $PERMITS = [
        "backend" => [
            "admin" => [
                "name" => "Admin",
                "icon" => "<i class='bi bi-arrow-through-heart'></i>",
                "bg" => "bg-primary",
                "tx" => "text-light",
                "color" => "primary",
                "creator" => ["admin"]
            ],
            "administrator" => [
                "name" => "Amministratore",
                "icon" => "<i class='bi bi-person-hearts'></i>",
                "bg" => "bg-light",
                "tx" => "text-dark",
                "color" => "light",
                "creator" => ["admin"]
            ],
            "api_user" => [
                "name" => "Api",
                "icon" => "<i class='bi bi-code-slash'></i>",
                "bg" => "bg-dark",
                "tx" => "text-white",
                "color" => "dark",
                "creator" => [ "admin" ]
            ],
            "links" => [
                "home" => "$PATH->backend/home/",
                "login" => "$PATH->backend/account/login/",
                "password-recovery" => "$PATH->backend/account/password-recovery/",
                "password-set" => "$PATH->backend/account/password-set/"
            ],
        ],
        "frontend" => [],
        "api" => [
            "api_internal_user" => [
                "name" => "API Interno",
                "icon" => "<i class='bi bi-arrow-through-heart'></i>",
                "bg" => "bg-primary",
                "tx" => "text-white",
                "color" => "primary"
            ],
            "api_public_access" => [
                "name" => "Utente API",
                "icon" => "<i class='bi bi-bug'></i>",
                "bg" => "bg-info",
                "tx" => "text-white",
                "color" => "info"
            ],
            "function" => [
                "creation" => "apiUser",
                "modify" => "apiUser",
                "info" => "infoApiUser"
            ],
            "links" => [
                "login" => "$PATH->site/account/login/",
                "sign-in" => "$PATH->site/account/sign-in/",
                "password-restore" => "$PATH->site/account/password-restore/",
                "password-recovery" => "$PATH->site/account/password-recovery/",
                "password-set" => "$PATH->site/account/password-set/"
            ],
        ]
    ];

    # Permessi CUSTOM
    require $ROOT."/custom/config/permissions.php";
    
    foreach ($CUSTOM_PERMITS as $key => $value) { foreach ($value as $k => $v) { $PERMITS[$key][$k] = $v; } }
