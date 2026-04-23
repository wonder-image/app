<?php

namespace Wonder\App\Resources\Config;

use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormInput;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\Resources\Support\SingletonResource;
use Wonder\Elements\Components\Card;
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
            'g_maps_place_id' => 'Place ID',
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
            'fatture_in_cloud_company_id' => 'Codice cliente',
            'fatture_in_cloud_token' => 'Token',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('api_key')->text()->disabled(),

            FormInput::key('gcp_project_id')->text(),
            FormInput::key('gcp_api_key')->text(),
            FormInput::key('gcp_client_api_key')->text(),
            FormInput::key('g_recaptcha_site_key')->text(),
            FormInput::key('g_maps_place_id')->text(),

            FormInput::key('google_oauth_client_id')->text(),
            FormInput::key('google_oauth_client_secret')->password(),
            FormInput::key('google_oauth_redirect_uri')->text(),
            FormInput::key('apple_oauth_client_id')->text(),
            FormInput::key('apple_oauth_team_id')->text(),
            FormInput::key('apple_oauth_key_id')->text(),
            FormInput::key('apple_oauth_redirect_uri')->text(),
            FormInput::key('apple_oauth_private_key')->password(),

            FormInput::key('mail_service')->select(static::mailServiceOptions())->required(),
            FormInput::key('brevo_api_key')->password(),
            FormInput::key('mail_host')->text(),
            FormInput::key('mail_port')->text(),
            FormInput::key('mail_username')->text(),
            FormInput::key('mail_password')->password(),

            FormInput::key('klaviyo_api_key')->password(),

            FormInput::key('stripe_test')
                ->select(['false' => 'Produzione', 'true' => 'Test'])
                ->required(),
            FormInput::key('stripe_account_id')->text()->readonly(),
            FormInput::key('stripe_test_account_id')->text()->readonly(),

            FormInput::key('fatture_in_cloud_company_id')->text(),
            FormInput::key('fatture_in_cloud_token')->text(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                SectionTitle::make('Wonder Image'),
                static::getInput('api_key')->columnSpan(12),
            ])->columns(12)->columnSpan(12),

            (new Card)->components([
                SectionTitle::make('Google Cloud Platform')
                    ->tooltip('Compila qui le chiavi progetto e i servizi collegati a Google.'),
                HelpText::make('Segui la documentazione <a href="https://wonder-image.gitbook.io/app/altro/servizi/google-cloud-platform" target="_blank" rel="noopener noreferrer">clicca qui</a>.'),
                static::getInput('gcp_project_id')->columnSpan(2),
                static::getInput('gcp_api_key')->columnSpan(5),
                static::getInput('gcp_client_api_key')->columnSpan(5),
                SectionTitle::make('Google reCAPTCHA*')->columnSpan(6),
                SectionTitle::make('Google Place*')->columnSpan(6),
                static::getInput('g_recaptcha_site_key')->columnSpan(6),
                static::getInput('g_maps_place_id')->columnSpan(6),
                HelpText::make('*Per utilizzare questa funzione è necessario compilare i campi di <b>Google Cloud Platform</b>.'),
            ])->columns(12)->columnSpan(9),

            (new Card)->components([
                SectionTitle::make('Stripe')
                    ->tooltip('Qui imposti ambiente e account collegati.'),
                static::getInput('stripe_test')->columnSpan(12),
                SectionTitle::make('Produzione'),
                static::getInput('stripe_account_id')->columnSpan(12),
                SectionTitle::make('Test'),
                static::getInput('stripe_test_account_id')->columnSpan(12),
            ])->columns(12)->columnSpan(3),

            (new Card)->components([
                SectionTitle::make('Login Federato (Google / Apple)')
                    ->tooltip('Usato per l’autenticazione social tramite Google e Apple.'),
                HelpText::make('Documentazione interna: <a href="https://wonder-image.gitbook.io/app/app/utente/auth-federata-google-apple" target="_blank" rel="noopener noreferrer">Auth Federata (GitBook)</a>.'),
                HelpText::make('Guide ufficiali: <a href="https://developers.google.com/identity/openid-connect/openid-connect" target="_blank" rel="noopener noreferrer">Google OpenID Connect</a> - <a href="https://developer.apple.com/documentation/sign_in_with_apple/sign_in_with_apple_js" target="_blank" rel="noopener noreferrer">Sign in with Apple JS</a>.'),
                static::getInput('google_oauth_client_id')->columnSpan(4),
                static::getInput('google_oauth_client_secret')->columnSpan(4),
                static::getInput('google_oauth_redirect_uri')->columnSpan(4),
                static::getInput('apple_oauth_client_id')->columnSpan(3),
                static::getInput('apple_oauth_team_id')->columnSpan(3),
                static::getInput('apple_oauth_key_id')->columnSpan(3),
                static::getInput('apple_oauth_redirect_uri')->columnSpan(3),
                static::getInput('apple_oauth_private_key')->columnSpan(12),
            ])->columns(12)->columnSpan(9),

            (new Card)->components([
                SectionTitle::make('Fatture in Cloud'),
                HelpText::make('Segui la documentazione <a href="https://wonder-image.gitbook.io/app/altro/servizi/fatture-in-cloud" target="_blank" rel="noopener noreferrer">clicca qui</a>.'),
                static::getInput('fatture_in_cloud_company_id')->columnSpan(12),
                static::getInput('fatture_in_cloud_token')->columnSpan(12),
            ])->columns(12)->columnSpan(3),

            (new Card)->components([
                SectionTitle::make('Server mail')
                    ->tooltip('Configura SMTP o Brevo per l’invio delle email.'),
                static::getInput('mail_service')->columnSpan(12),
                static::getInput('brevo_api_key')->columnSpan(12),
                static::getInput('mail_host')->columnSpan(8),
                static::getInput('mail_port')->columnSpan(4),
                static::getInput('mail_username')->columnSpan(12),
                static::getInput('mail_password')->columnSpan(12),
            ])->columns(12)->columnSpan(6),

            (new Card)->components([
                SectionTitle::make('Klaviyo'),
                HelpText::make('<a href="https://developers.klaviyo.com/en/reference/api_overview" target="_blank" rel="noopener noreferrer">Apri documentazione API</a>.'),
                static::getInput('klaviyo_api_key')->columnSpan(12),
            ])->columns(12)->columnSpan(6),
        ])->columns(12);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('Set Up', 'set-up', 'bi-gear')
            ->title('Credenziali')
            ->order(70)
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
