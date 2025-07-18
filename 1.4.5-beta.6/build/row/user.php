<?php

    if (!sqlSelect('user', [ 'username' => '@wonder' ], 1)->exists) {
        
        $values = [
            "name" => $_ENV['USER_NAME'],
            "surname" => $_ENV['USER_SURNAME'],
            "email" => $_ENV['USER_EMAIL'],
            "username" => $_ENV['USER_USERNAME'],
            "password" => $_ENV['USER_PASSWORD'],
            "authority" => ["admin"],
            "area" => ["backend"],
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
            "authority" => ["api_internal_user"],
            "area" => ["api"],
            "active" => "true",
            "allowed_domains" => [$PAGE->domain]
        ];

        user($values);

    }