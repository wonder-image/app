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

    # Se le API sono attive
    $API_STATUS = json_decode(wiApi('/auth/status/'), true);

    if ($API_STATUS['success']) {
        if ($API_STATUS['response']['active'] == true) {

            # Se non ci sono le credenziali email le aggiungo
            if (sqlSelect('security', ['id' => 1], 1)->row['mail_host'] == "") {
            
                $MAIL = json_decode(wiApi('/mail/server/'), true);

                if ($MAIL['success']) {

                    sqlModify(
                        'security', 
                        [
                            "mail_host" => $MAIL['response']['host'],
                            "mail_username" => $MAIL['response']['username'],
                            "mail_password" => $MAIL['response']['password'],
                            "mail_port" => $MAIL['response']['port']
                        ],
                        'id',
                        1
                    );

                }
                    
            }
            
            # Aggiungo le chiavi di stripe se non ci sono
            if (sqlSelect('security', ['id' => 1], 1)->row['stripe_private_key'] == "") {
                
                $STRIPE = json_decode(wiApi('/service/stripe/credentials/'), true);

                if ($STRIPE['success']) {

                    sqlModify(
                        'security', 
                        [
                            "stripe_private_key" => $STRIPE['response']['private_key'],
                            "stripe_test_key" => $STRIPE['response']['test_key']
                        ],
                        'id',
                        1
                    );

                }

            }

        }
    }