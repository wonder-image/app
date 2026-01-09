<?php

    $TABLE->ANALYTICS = [
        "tag_manager" => [],
        "active_tag_manager" => [],
        "pixel_facebook" => [],
        "active_pixel_facebook" => []
    ];

    $TABLE->SECURITY = [
        "api_key" => [ "sql" => [ "length" => 23 ] ],
        "gcp_project_id" => [],
        "gcp_api_key" => [],
        "gcp_client_api_key" => [],
        "g_recaptcha_site_key" => [],
        "g_maps_place_id" => [],
        "stripe_account_id" => [],
        "stripe_test_account_id" => [],
        "stripe_private_key" => [],
        "stripe_test_key" => [],
        "stripe_test" => [ "sql" => [ "default" => 'false' ] ],
        "fatture_in_cloud_app_id" => [],
        "fatture_in_cloud_client_id" => [],
        "fatture_in_cloud_client_secret" => [],
        "fatture_in_cloud_token" => [],
        "ipinfo_api_key" => [],
        "mail_host" => [],
        "mail_username" => [],
        "mail_password" => [],
        "mail_port" => [],
        "encrypt" => [ "sql" => [ "default" => 'false' ] ]
    ];