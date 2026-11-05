<?php

namespace Wonder\App\Resources\Config;

use InvalidArgumentException;
use Wonder\App\Integrations\Integrations;
use Wonder\App\LegacyGlobals;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\Inputs\InputPassword;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\Resources\Support\SingletonResource;
use Wonder\Http\Route;

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
            'g_recaptcha_score_site_key' => 'Chiave Sito',
            'g_recaptcha_badge' => 'Badge',
            'g_maps_place_id' => 'Place ID',
            'g_maps_map_id' => 'Map ID',
            'g_tag_manager_id' => 'ID contenitore (GTM-XXXXXXX)',
            'g_tag_manager_active' => 'Stato',
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
            FormField::key('g_recaptcha_score_site_key')->text(),
            FormField::key('g_recaptcha_badge')->select(['true' => 'Visibile', 'false' => 'Nascosto'])->value('true')->required(),
            FormField::key('g_maps_place_id')->text(),
            FormField::key('g_maps_map_id')->text(),
            FormField::key('g_tag_manager_id')->text(),
            FormField::key('g_tag_manager_active')->select(['true' => 'Attivo', 'false' => 'Spento'])->value('true')->required(),

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
            FormField::key('stripe_webhook_secret')->password()->readonly(),
            FormField::key('stripe_test_webhook_secret')->password()->readonly(),

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

    public static function pageSchema(): PageSchema
    {
        return parent::pageSchema()
            ->view('form', (string) LegacyGlobals::get('ROOT_APP', '').'/view/pages/backend/config/security/integrations.php');
    }

    /**
     * Ogni modal salva una sola integrazione: passano solo i suoi campi, mai
     * quelli del `.env`, quelli in sola lettura o le password lasciate vuote
     * (restano quelle salvate). Il resto della riga non si tocca.
     */
    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        if ($context !== 'backend') {
            return $values;
        }

        $integration = Integrations::find((string) ($values['integration'] ?? ''));

        if ($integration === null || $integration->readonly()) {
            throw new InvalidArgumentException('Integrazione non valida.');
        }

        $fields = [];

        foreach (static::formSchema() as $field) {
            $fields[(string) $field->name] = $field;
        }

        $filtered = [];

        foreach (array_keys($integration->fields()) as $column) {
            $field = $fields[$column] ?? null;

            if ($field === null || !array_key_exists($column, $values) || Integrations::env($column)
                || static::locked($field)) {
                continue;
            }

            if ($field instanceof InputPassword && trim((string) $values[$column]) === '') {
                continue;
            }

            $filtered[$column] = $values[$column];
        }

        return $filtered;
    }

    /** Campo in sola lettura o disabilitato nel `formSchema()`: non si scrive dal modal. */
    public static function locked(object $field): bool
    {
        $attributes = preg_split('/\s+/', trim((string) $field->get('attribute'))) ?: [];

        return in_array('readonly', $attributes, true) || in_array('disabled', $attributes, true);
    }

    /** Pagina Credenziali (singleton, id fisso). */
    public static function editUrl(): string
    {
        return __r('backend.resource.'.static::slug().'.edit', ['id' => static::singletonRecordId()]);
    }

    public static function mailTestUrl(): string
    {
        return __r('backend.resource.'.static::slug().'.mail-test');
    }

    /** POST `/mail-test/`: prova le credenziali email del modal, senza salvarle, e torna alla pagina. */
    public static function registerBackendRoutes(string $rootApp, string $slug): void
    {
        $permissions = (array) static::permissionSchema()->get('backend');

        Route::post('/mail-test/', $rootApp.'/http/backend/config/security/mail-test.php', [
            'resource' => $slug,
            'resource_action' => 'mail-test',
        ])->name('mail-test')->permit($permissions['update'] ?? ['admin']);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('dev')
            ->group('api-services', 'API e servizi', 30)
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
