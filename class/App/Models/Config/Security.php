<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class Security extends Model
{
    public static string $table = 'security';
    public static string $folder = 'app/config/credentials';
    public static string $icon = 'bi bi-key';

    public static function tableSchema(): array
    {
        return [
            Column::key('api_key'),
            Column::key('gcp_project_id'),
            Column::key('gcp_api_key'),
            Column::key('gcp_client_api_key'),
            Column::key('google_oauth_client_id'),
            Column::key('google_oauth_client_secret'),
            Column::key('google_oauth_redirect_uri'),
            Column::key('apple_oauth_client_id'),
            Column::key('apple_oauth_team_id'),
            Column::key('apple_oauth_key_id'),
            Column::key('apple_oauth_private_key')->type('TEXT'),
            Column::key('apple_oauth_redirect_uri'),
            Column::key('g_recaptcha_site_key'),
            Column::key('g_maps_place_id'),
            Column::key('mail_service')->default('phpmailer'),
            Column::key('brevo_api_key'),
            Column::key('mail_host'),
            Column::key('mail_port')->int()->null(),
            Column::key('mail_username'),
            Column::key('mail_password'),
            Column::key('klaviyo_api_key'),
            Column::key('ipinfo_api_key'),
            Column::key('stripe_test')->default('false'),
            Column::key('stripe_test_key'),
            Column::key('stripe_private_key'),
            Column::key('stripe_account_id'),
            Column::key('stripe_test_account_id'),
            Column::key('fatture_in_cloud_app_id'),
            Column::key('fatture_in_cloud_client_id'),
            Column::key('fatture_in_cloud_client_secret'),
            Column::key('fatture_in_cloud_company_id'),
            Column::key('fatture_in_cloud_token')->type('TEXT'),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('api_key')->text(),
            Field::key('gcp_project_id')->text(),
            Field::key('gcp_api_key')->text(),
            Field::key('gcp_client_api_key')->text(),
            Field::key('google_oauth_client_id')->text(),
            Field::key('google_oauth_client_secret')->text(),
            Field::key('google_oauth_redirect_uri')->text(),
            Field::key('apple_oauth_client_id')->text(),
            Field::key('apple_oauth_team_id')->text(),
            Field::key('apple_oauth_key_id')->text(),
            Field::key('apple_oauth_private_key')->text(),
            Field::key('apple_oauth_redirect_uri')->text(),
            Field::key('g_recaptcha_site_key')->text(),
            Field::key('g_maps_place_id')->text(),
            Field::key('mail_service')->text(),
            Field::key('brevo_api_key')->text(),
            Field::key('mail_host')->text(),
            Field::key('mail_port')->number(),
            Field::key('mail_username')->text(),
            Field::key('mail_password')->text(),
            Field::key('klaviyo_api_key')->text(),
            Field::key('ipinfo_api_key')->text(),
            Field::key('stripe_test')->text(),
            Field::key('stripe_test_key')->text(),
            Field::key('stripe_private_key')->text(),
            Field::key('stripe_account_id')->text(),
            Field::key('stripe_test_account_id')->text(),
            Field::key('fatture_in_cloud_app_id')->text(),
            Field::key('fatture_in_cloud_client_id')->text(),
            Field::key('fatture_in_cloud_client_secret')->text(),
            Field::key('fatture_in_cloud_company_id')->text(),
            Field::key('fatture_in_cloud_token')->text(),
        ];
    }
}
