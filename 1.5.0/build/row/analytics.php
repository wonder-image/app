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

        $values = [ "api_key" => $API->key ];
        sqlInsert('security', $values);

    }

    # Se le API sono attive
    $API_STATUS = json_decode(wiApi('/auth/status/'), true);

    if ($API_STATUS['success']) {
        if ($API_STATUS['response']['active'] == true) {

            $VALUES = [];

            # Se non ci sono le credenziali email le aggiungo
            if (sqlSelect('security', ['id' => 1], 1)->row['mail_host'] == "") {
            
                $MAIL = json_decode(wiApi('/mail/server/'), true);

                if ($MAIL['success']) {

                    $VALUES["mail_host"] = $MAIL['response']['host'];
                    $VALUES["mail_username"] = $MAIL['response']['username'];
                    $VALUES["mail_password"] = $MAIL['response']['password'];
                    $VALUES["mail_port"] = $MAIL['response']['port'];

                }
                    
            }
            
            # Aggiungo le chiavi di stripe se non ci sono
            if (sqlSelect('security', ['id' => 1], 1)->row['stripe_private_key'] == "") {
                
                $STRIPE = json_decode(wiApi('/service/stripe/credentials/'), true);

                if ($STRIPE['success']) {

                    $VALUES["stripe_private_key"] = $STRIPE['response']['private_key'];
                    $VALUES["stripe_test_key"] = $STRIPE['response']['test_key'];

                }

            }

            # Aggiungo le chiavi di fatture in cloud se non ci sono
            if (sqlSelect('security', ['id' => 1], 1)->row['fatture_in_cloud_app_id'] == "") {
                
                $FIC = json_decode(wiApi('/service/fatture-in-cloud/credentials/'), true);
                if ($FIC['success']) {

                    $VALUES["fatture_in_cloud_app_id"] = $FIC['response']['app_id'];
                    $VALUES["fatture_in_cloud_client_id"] = $FIC['response']['client_id'];
                    $VALUES["fatture_in_cloud_client_secret"] = $FIC['response']['client_secret'];

                }

            }

            # Aggiungo la chiave di IP Info se non c'è
            if (sqlSelect('security', ['id' => 1], 1)->row['ipinfo_api_key'] == "") {
                
                $STRIPE = json_decode(wiApi('/service/ipinfo/credentials/'), true);

                if ($STRIPE['success']) {

                    $VALUES["ipinfo_api_key"] = $STRIPE['response']['api_key'];

                }

            }

            if (!empty($VALUES)) { sqlModify( 'security', $VALUES, 'id', 1 ); }

        }
    }