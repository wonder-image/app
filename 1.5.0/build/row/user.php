<?php

    $adminUsername = trim((string) ($_ENV['USER_USERNAME'] ?? 'admin'));

    if (!sqlSelect('user', [ 'username' => $adminUsername ], 1)->exists) {

        $values = [
            "name" => $_ENV['USER_NAME'],
            "surname" => $_ENV['USER_SURNAME'],
            "email" => $_ENV['USER_EMAIL'],
            "username" => $adminUsername,
            "password" => $_ENV['USER_PASSWORD'],
            "authority" => "admin",
            "area" => "backend",
            "active" => "true"
        ];

        user($values);

    }

    if (!sqlSelect('user', [ 'username' => '@system' ], 1)->exists) {
        
        $values = [
            "name" => "API",
            "surname" => "System",
            "email" => "system@".$PAGE->domain,
            "username" => "@system",
            "password" => $_ENV['USER_PASSWORD'],
            "authority" => "api_internal_user",
            "area" => "api",
            "active" => "true",
            "allowed_domains" => [$PAGE->domain]
        ];

        user($values);

    }

    if (!sqlSelect('user', [ 'username' => '@github' ], 1)->exists) {
        
        $values = [
            "name" => "GitHub",
            "surname" => "Actions",
            "email" => "github@".$PAGE->domain,
            "username" => "@github",
            "password" => $_ENV['USER_PASSWORD'],
            "authority" => "api_public_access",
            "area" => "api",
            "active" => "true",
            "allowed_domains" => [$PAGE->domain]
        ];

        user($values);

    }
