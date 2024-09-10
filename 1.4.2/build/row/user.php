<?php

    if (!sqlSelect('user', ['username' => '@wonder'], 1)->exists) {
        
        $values = [
            "name" => sanitize($_ENV['USER_NAME']),
            "surname" => sanitize($_ENV['USER_SURNAME']),
            "email" => sanitize($_ENV['USER_EMAIL']),
            "username" => sanitize($_ENV['USER_USERNAME']),
            "password" => hashPassword($_ENV['USER_PASSWORD']),
            "authority" => json_encode(["admin"]),
            "area" => json_encode(["backend"]),
            "active" => "true"
        ];

        sqlInsert('user', $values);

    }