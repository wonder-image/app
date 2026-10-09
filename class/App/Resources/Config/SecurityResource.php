<?php

namespace Wonder\App\Resources\Config;

use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\Path;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\Resources\Support\SingletonResource;
use Wonder\Elements\Components\Badge;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\HelpText;
use Wonder\Elements\Components\SectionTitle;
use Wonder\Elements\Form\Form;

final class SecurityResource extends SingletonResource
{
    public static string $model = \Wonder\App\Models\Config\Security::class;

    public static function textSchema(): array
    {
        return [
            'label' => 'credenziale',
            'plural_label' => 'credenziali',
            'last' => 'ultime',
            'all' => 'tutte',
            'article' => 'le',
            'full' => 'configurata',
            'empty' => 'vuota',
            'this' => 'questa',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'api_key' => 'Api Key',
            'gcp_project_id' => 'ID Progetto',
            'gcp_api_key' => 'Chiave Privata',
            'gcp_client_api_key' => 'Chiave Pubblica',
            'g_recaptcha_site_key' => 'Chiave Sito',
            'g_recaptcha_secret_key' => 'Chiave Segreta',
            'g_maps_place_id' => 'Place ID',
            'g_maps_map_id' => 'Map ID',
            'google_oauth_client_id' => 'Google Client ID',
            'google_oauth_client_secret' => 'Google Client Secret',
            'google_oauth_redirect_uri' => 'Google Redirect URI',
            'apple_oauth_client_id' => 'Apple Service ID (Client ID)',
            'apple_oauth_team_id' => 'Apple Team ID',
            'apple_oauth_key_id' => 'Apple Key ID',
            'apple_oauth_private_key' => 'Apple Private Key (.p8)',
            'apple_oauth_redirect_uri' => 'Apple Redirect URI',
            'mail_service' => 'Servizio',
            'brevo_api_key' => 'Brevo API Key',
            'mail_host' => 'Host',
            'mail_port' => 'Porta',
            'mail_username' => 'Username',
            'mail_password' => 'Password',
            'klaviyo_api_key' => 'Klaviyo API Key',
            'stripe_test' => 'Ambiente',
            'stripe_account_id' => 'Account ID',
            'stripe_test_account_id' => 'Account ID Test',
            'stripe_public_key' => 'Chiave pubblica',
            'stripe_test_public_key' => 'Chiave pubblica Test',
            'stripe_webhook_secret' => 'Segreto webhook',
            'stripe_test_webhook_secret' => 'Segreto webhook Test',
            'paypal_live' => 'Ambiente',
            'paypal_client_id' => 'Client ID',
            'paypal_client_secret' => 'Client Secret',
            'nexi_prod' => 'Ambiente',
            'nexi_api_key' => 'API Key',
            'nexi_alias' => 'Alias XPay classico',
            'nexi_mac_key' => 'Chiave MAC XPay classico',
            'fatture_in_cloud_company_id' => 'Codice cliente',
            'fatture_in_cloud_token' => 'Token',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('api_key')->text()->disabled(),

            FormField::key('gcp_project_id')->text(),
            FormField::key('gcp_api_key')->text(),
            FormField::key('gcp_client_api_key')->text(),
            FormField::key('g_recaptcha_site_key')->text(),
            FormField::key('g_recaptcha_secret_key')->text(),
            FormField::key('g_maps_place_id')->text(),
            FormField::key('g_maps_map_id')->text(),

            FormField::key('google_oauth_client_id')->text(),
            FormField::key('google_oauth_client_secret')->password()->autocomplete('new-password'),
            FormField::key('google_oauth_redirect_uri')->text(),
            FormField::key('apple_oauth_client_id')->text(),
            FormField::key('apple_oauth_team_id')->text(),
            FormField::key('apple_oauth_key_id')->text(),
            FormField::key('apple_oauth_redirect_uri')->text(),
            FormField::key('apple_oauth_private_key')->password()->autocomplete('new-password'),

            FormField::key('mail_service')->select(static::mailServiceOptions())->required(),
            FormField::key('brevo_api_key')->password()->autocomplete('new-password'),
            FormField::key('mail_host')->text(),
            FormField::key('mail_port')->text(),
            FormField::key('mail_username')->text(),
            FormField::key('mail_password')->password()->autocomplete('new-password'),

            FormField::key('klaviyo_api_key')->password()->autocomplete('new-password'),

            FormField::key('stripe_test')
                ->select(['false' => 'Produzione', 'true' => 'Test'])
                ->required(),
            FormField::key('stripe_account_id')->text()->readonly(),
            FormField::key('stripe_test_account_id')->text()->readonly(),
            FormField::key('stripe_public_key')->text(),
            FormField::key('stripe_test_public_key')->text(),
            FormField::key('stripe_webhook_secret')->text()->readonly(),
            FormField::key('stripe_test_webhook_secret')->text()->readonly(),

            FormField::key('paypal_live')
                ->select(['false' => 'Sandbox', 'true' => 'Produzione'])
                ->required(),
            FormField::key('paypal_client_id')->text(),
            FormField::key('paypal_client_secret')->password(),

            FormField::key('nexi_prod')
                ->select(['false' => 'Test', 'true' => 'Produzione'])
                ->required(),
            FormField::key('nexi_api_key')->password(),
            FormField::key('nexi_alias')->text(),
            FormField::key('nexi_mac_key')->password(),

            FormField::key('fatture_in_cloud_company_id')->text(),
            FormField::key('fatture_in_cloud_token')->text(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([

            (new Container)->components([

                (new Card)->components([
                    SectionTitle::make('Wonder Image')->columnSpan(12),
                    static::getInput('api_key')->columnSpan(12),
                ])->columns(12)->columnSpan(2),
                
                (new Card)->components([
                    SectionTitle::make('Google Cloud Platform')
                        ->tooltip('Compila qui le chiavi progetto e i servizi collegati a Google.')
                        ->columnSpan(12),
                    HelpText::make('Segui la documentazione <a href="https://wonder-image.gitbook.io/app/altro/servizi/google-cloud-platform" target="_blank" rel="noopener noreferrer">clicca qui</a>.')
                        ->columnSpan(12),
                    static::getInput('gcp_project_id')->columnSpan(2),
                    static::getInput('gcp_api_key')->columnSpan(5),
                    static::getInput('gcp_client_api_key')->columnSpan(5),
                    SectionTitle::make('Google reCAPTCHA*')->columnSpan(12),
                    static::getInput('g_recaptcha_site_key')->columnSpan(4),
                    static::getInput('g_recaptcha_secret_key')->columnSpan(4),
                    SectionTitle::make('Google Maps*')->columnSpan(12),
                    static::getInput('g_maps_place_id')->columnSpan(4),
                    static::getInput('g_maps_map_id')->columnSpan(4),
                    SectionTitle::make('Google Auth Platform*')->columnSpan(12),
                    static::getInput('google_oauth_client_id')->columnSpan(6),
                    static::getInput('google_oauth_client_secret')->columnSpan(6),
                    HelpText::make('*Per utilizzare questa funzione è necessario compilare i campi di <b>Google Cloud Platform</b>.')
                    ->columnSpan(12),
                ])->columns(12)->columnSpan(2),

                (new Card)->components([
                    SectionTitle::make('Klaviyo'),
                    HelpText::make('<a href="https://developers.klaviyo.com/en/reference/api_overview" target="_blank" rel="noopener noreferrer">Apri documentazione API</a>.')
                        ->columnSpan(12),
                    static::getInput('klaviyo_api_key')
                        ->columnSpan(12),
                ])->columns(12)->columnSpan(2),

                (new Card)->components([
                    SectionTitle::make('Server mail')
                        ->tooltip('Configura SMTP o Brevo per l’invio delle email.')
                        ->columnSpan(12),
                    static::getInput('mail_service')->columnSpan(12),
                    static::getInput('brevo_api_key')->columnSpan(12),
                    static::getInput('mail_host')->columnSpan(8),
                    static::getInput('mail_port')->columnSpan(4),
                    static::getInput('mail_username')->columnSpan(12),
                    static::getInput('mail_password')->columnSpan(12),
                ])->columns(12)->columnSpan(1),

            ])->columns(2)->columnSpan(9),

            (new Container)->components([

                (new Card)->components([

                    SectionTitle::make('Stripe')
                        ->tooltip('Qui imposti ambiente e account collegati.')
                        ->columnSpan(12),

                    static::getInput('stripe_test')->columnSpan(12),

                    SectionTitle::make('Produzione')
                        ->columnSpan(4),
                    Badge::to((new Path)->appApi.'/service/stripe/onboarding/?account=production', 'Collega')
                        ->variant('dark')
                        ->addClass('float-end')
                        ->columnSpan(4),
                    Badge::to((new Path)->appApi.'/service/stripe/connect/?account=production', 'Collega webhook')
                        ->variant('dark')
                        ->addClass('float-end')
                        ->columnSpan(4),
                    static::getInput('stripe_account_id')->columnSpan(12),
                    static::getInput('stripe_public_key')->columnSpan(6),
                    static::getInput('stripe_webhook_secret')->columnSpan(6),

                    SectionTitle::make('Test')
                        ->columnSpan(4),
                    Badge::to((new Path)->appApi.'/service/stripe/onboarding/?account=test', 'Collega')
                        ->variant('dark')
                        ->addClass('float-end')
                        ->columnSpan(4),
                    Badge::to((new Path)->appApi.'/service/stripe/connect/?account=test', 'Collega webhook')
                        ->variant('dark')
                        ->addClass('float-end')
                        ->columnSpan(4),
                    static::getInput('stripe_test_account_id')->columnSpan(12),
                    static::getInput('stripe_test_public_key')->columnSpan(6),
                    static::getInput('stripe_test_webhook_secret')->columnSpan(6),
                        
                ])->columns(12)->columnSpan(1),

                (new Card)->components([
                    SectionTitle::make('PayPal')->columnSpan(12),
                    static::getInput('paypal_live')->columnSpan(12),
                    static::getInput('paypal_client_id')->columnSpan(12),
                    static::getInput('paypal_client_secret')->columnSpan(12),
                ])->columns(12)->columnSpan(1),

                (new Card)->components([
                    SectionTitle::make('Nexi')->columnSpan(12),
                    HelpText::make('Compila l\'API Key per XPay Web/Global oppure alias e chiave MAC per XPay classico.')
                        ->columnSpan(12),
                    static::getInput('nexi_prod')->columnSpan(12),
                    static::getInput('nexi_api_key')->columnSpan(12),
                    static::getInput('nexi_alias')->columnSpan(12),
                    static::getInput('nexi_mac_key')->columnSpan(12),
                ])->columns(12)->columnSpan(1),


                (new Card)->components([
                    SectionTitle::make('Fatture in Cloud')
                    ->columnSpan(12),
                    HelpText::make('Segui la documentazione <a href="https://wonder-image.gitbook.io/app/altro/servizi/fatture-in-cloud" target="_blank" rel="noopener noreferrer">clicca qui</a>.')
                    ->columnSpan(12),
                    static::getInput('fatture_in_cloud_company_id')->columnSpan(12),
                    static::getInput('fatture_in_cloud_token')->columnSpan(12),
                ])->columns(12)->columnSpan(1),

            ])->columnSpan(3)->columns(1)



        ])->columns(12);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('dev')
            ->inGroup('api-services')
            ->title('Credenziali')
            ->order(20)
            ->authority(['admin']);
    }

    private static function mailServiceOptions(): array
    {
        $options = [];

        foreach ((array) mailService() as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (is_array($value)) {
                $label = trim((string) ($value['text'] ?? $value['name'] ?? ''));
            } else {
                $label = trim((string) $value);
            }

            if ($label !== '') {
                $options[$key] = $label;
            }
        }

        return $options;
    }

}
