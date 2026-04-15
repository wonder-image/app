<?php

    $adminUsername = trim((string) ($_ENV['USER_USERNAME'] ?? \Wonder\App\RuntimeDefaults::adminUsername()));
    $adminName = trim((string) ($_ENV['USER_NAME'] ?? \Wonder\App\RuntimeDefaults::adminName()));
    $adminSurname = trim((string) ($_ENV['USER_SURNAME'] ?? \Wonder\App\RuntimeDefaults::adminSurname()));
    $adminEmail = trim((string) ($_ENV['USER_EMAIL'] ?? \Wonder\App\RuntimeDefaults::adminEmail()));

    if (!sqlSelect('user', [ 'username' => $adminUsername ], 1)->exists) {

        $values = [
            "name" => $adminName,
            "surname" => $adminSurname,
            "email" => $adminEmail,
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
            "email" => \Wonder\App\RuntimeDefaults::systemEmail(),
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
            "email" => \Wonder\App\RuntimeDefaults::githubEmail(),
            "username" => "@github",
            "password" => $_ENV['USER_PASSWORD'],
            "authority" => "api_public_access",
            "area" => "api",
            "active" => "true",
            "allowed_domains" => [$PAGE->domain]
        ];

        user($values);

    }
