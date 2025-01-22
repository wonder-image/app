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
            "api_key" => $API->key,
            "gcp_project_id" => $API->gcp_project_id,
            "gcp_api_key" => $API->gcp_api_key
        ];

        sqlInsert('security', $values);

    } else {

        $row = sqlSelect('security', [ 'id' => 1 ], 1)->row;

        if (empty($row['gcp_project_id']) || empty($row['gcp_api_key'])) {
                        
            $values = [
                "gcp_project_id" => $API->gcp_project_id,
                "gcp_api_key" => $API->gcp_api_key
            ];
    
            sqlModify('security', $values, 'id', 1);

        }

    }