<?php

    if (sqlSelect('analytics', ['id' => 1], 1)->Nrow == 0) {
            
        $values = [
            "tag_manager" => ""
        ];

        sqlInsert('analytics', $values);

    }

?>