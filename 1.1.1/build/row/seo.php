<?php

    if (sqlSelect('seo', ['id' => 1], 1)->Nrow == 0) {
            
        $values = [
            "author" => "Andrea Marinoni",
            "copyright" => "Wonder Image",
            "creator" => "wonderimage",
            "reply" => "marinoni@wonderimage.it"
        ];

        sqlInsert('seo', $values);

    }

?>