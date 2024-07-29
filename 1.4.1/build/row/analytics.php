<?php

    if (!sqlSelect('analytics', ['id' => 1], 1)->exists) {
            
        $values = [
            "tag_manager" => "",
            "active_tag_manager" => "false",
            "pixel_facebook" => "",
            "active_pixel_facebook" => "false"
        ];

        sqlInsert('analytics', $values);

    }


    if (!sqlSelect('security', ['id' => 1], 1)->exists) {
            
        $values = [
            "api_key" => $API->key
        ];

        sqlInsert('security', $values);

    }