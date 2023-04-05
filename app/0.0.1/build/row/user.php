<?php

    if (sqlSelect('user', ['username' => '@wonder'], 1)->Nrow == 0) {
        
        $values = [
            "name" => sanitize("Andrea"),
            "surname" => sanitize("Marinoni"),
            "email" => sanitize("marinoni@wonderimage.it"),
            "username" => sanitize("@wonder"),
            "password" => hashPassword("admin"),
            "authority" => json_encode(["admin"]),
            "area" => json_encode(["backend"]),
            "active" => "true"
        ];

        sqlInsert('user', $values);

    }
    
?>