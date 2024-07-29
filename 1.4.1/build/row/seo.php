<?php

    if (!sqlSelect('seo', ['id' => 1], 1)->exists) {
            
        $values = [
            "author" => "Andrea Marinoni",
            "copyright" => "Wonder Image",
            "creator" => "wonderimage",
            "reply" => "marinoni@wonderimage.it"
        ];

        sqlInsert('seo', $values);

    }