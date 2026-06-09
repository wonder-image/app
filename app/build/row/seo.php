<?php

    if (!sqlSelect('seo', ['id' => 1], 1)->exists) {
            
        $values = [
            "author" => "Andrea Marinoni",
            "copyright" => "Wonder Image",
            "creator" => "wonderimage",
            "reply" => "marinoni@wonderimage.it"
        ];
        $values['id'] = 1;

        sqlInsert('seo', $values);

    }