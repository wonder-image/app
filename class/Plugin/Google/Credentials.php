<?php

    namespace Wonder\Plugin\Google;

    use Wonder\App\Credentials as DefaultCredentials;

    class Credentials {

        static function get() {

            $default = DefaultCredentials::api();

            $RETURN = (object) [];

            $RETURN->recaptcha_site_key = $default->g_recaptcha_site_key;
            $RETURN->maps_place_id = $default->g_maps_place_id;
            $RETURN->gcp_project_id = $default->gcp_project_id;
            $RETURN->gcp_api_key = $default->gcp_api_key;
            
            return $RETURN;
            
        }

    }