<?php

    $PERMITS = [
        "backend" => [
            "admin" => [
                "name" => "Admin",
                "icon" => "<i class='bi bi-code-slash'></i>",
                "bg" => "bg-dark",
                "tx" => "text-light",
                "color" => "dark",
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
            "links" => [
                "home" => "$PATH->backend/home/",
                "login" => "$PATH->backend/account/login/",
                "password-recovery" => "$PATH->backend/account/password-recovery/",
                "password-set" => "$PATH->backend/account/password-set/"
            ],
        ],
        "frontend" => []
    ];

    # Permessi CUSTOM
    require $ROOT."/custom/config/permissions.php";
    
    foreach ($CUSTOM_PERMITS as $key => $value) { foreach ($value as $k => $v) { $PERMITS[$key][$k] = $v; } }
