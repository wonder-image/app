<?php

    if (sqlSelect('analytics', ['id' => 1], 1)->Nrow == 0) {
            
        $values = [
            "tag_manager" => "",
            "active_tag_manager" => "false",
            "pixel_facebook" => "",
            "active_pixel_facebook" => "false"
        ];

        sqlInsert('analytics', $values);

    }

?>