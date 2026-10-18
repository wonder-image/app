<?php

    if (!sqlSelect('analytics', ['id' => 1], 1)->exists) {
            
        $values = \Wonder\App\SeedDefaults::analyticsRow();
        $values['id'] = 1;

        sqlInsert('analytics', $values);

    }


    if (!sqlSelect('security', ['id' => 1], 1)->exists) {

        $values = \Wonder\App\SeedDefaults::securityRow((string) ($API->key ?? ''));
        $values['id'] = 1;
        sqlInsert('security', $values);

    }

    # Se le API sono attive
    $API_STATUS = wiApiJson('/auth/status/');

    if (is_array($API_STATUS) && ($API_STATUS['success'] ?? false)) {
        if (($API_STATUS['response']['active'] ?? false) == true) {

            $VALUES = [];

            # Se non ci sono le credenziali email le aggiungo
            if (sqlSelect('security', ['id' => 1], 1)->row['mail_host'] == "") {
            
                $MAIL = wiApiJson('/mail/server/');

                if (is_array($MAIL) && ($MAIL['success'] ?? false)) {

                    $VALUES["mail_host"] = $MAIL['response']['host'];
                    $VALUES["mail_username"] = $MAIL['response']['username'];
                    $VALUES["mail_password"] = $MAIL['response']['password'];
                    $VALUES["mail_port"] = $MAIL['response']['port'];

                }
                    
            }

            # Imposto default servizio mail se assente
            if (empty(sqlSelect('security', ['id' => 1], 1)->row['mail_service'])) {
                $VALUES["mail_service"] = "phpmailer";
            }
            
            # Aggiungo le chiavi di stripe che mancano: quelle già salvate restano
            $STRIPE_ROW = sqlSelect('security', ['id' => 1], 1)->row;
            $STRIPE_KEYS = [
                'stripe_private_key' => 'private_key',
                'stripe_test_key' => 'test_key',
                'stripe_public_key' => 'public_key',
                'stripe_test_public_key' => 'test_public_key',
            ];
            $STRIPE_MISSING = array_filter($STRIPE_KEYS, fn ($column) => trim((string) ($STRIPE_ROW[$column] ?? '')) === '', ARRAY_FILTER_USE_KEY);

            if (!empty($STRIPE_MISSING)) {

                $STRIPE = wiApiJson('/service/stripe/credentials/');

                if (is_array($STRIPE) && ($STRIPE['success'] ?? false)) {

                    foreach ($STRIPE_MISSING as $column => $key) {
                        // Le chiavi pubbliche arrivano solo se il servizio centrale le dà.
                        $value = trim((string) ($STRIPE['response'][$key] ?? ''));

                        if ($value !== '') {
                            $VALUES[$column] = $value;
                        }
                    }

                }

            }

            # Aggiungo le chiavi di fatture in cloud se non ci sono
            if (sqlSelect('security', ['id' => 1], 1)->row['fatture_in_cloud_app_id'] == "") {
                
                $FIC = wiApiJson('/service/fatture-in-cloud/credentials/');
                if (is_array($FIC) && ($FIC['success'] ?? false)) {

                    $VALUES["fatture_in_cloud_app_id"] = $FIC['response']['app_id'];
                    $VALUES["fatture_in_cloud_client_id"] = $FIC['response']['client_id'];
                    $VALUES["fatture_in_cloud_client_secret"] = $FIC['response']['client_secret'];

                }

            }

            # Aggiungo la chiave di IP Info se non c'è
            if (sqlSelect('security', ['id' => 1], 1)->row['ipinfo_api_key'] == "") {
                
                $STRIPE = wiApiJson('/service/ipinfo/credentials/');

                if (is_array($STRIPE) && ($STRIPE['success'] ?? false)) {

                    $VALUES["ipinfo_api_key"] = $STRIPE['response']['api_key'];

                }

            }

            if (!empty($VALUES)) { sqlModify( 'security', $VALUES, 'id', 1 ); }

        }
    }
