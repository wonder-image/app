<?php

namespace Wonder\App\Support\GoogleCloud;

use Wonder\App\Module\Contracts\ModuleGoogleApis;
use Wonder\App\Module\Registry;

/**
 * Elenco delle API Google che il sito deve abilitare nel suo progetto Cloud.
 *
 * Sorgenti, in ordine: core, moduli abilitati che implementano
 * `ModuleGoogleApis`, sito (`custom/config/google-apis.php`, che ritorna un
 * iterable di `GoogleApi`). La stessa chiave dichiarata da più sorgenti viene
 * unita con `GoogleApi::merge()`: un modulo può rendere obbligatoria un'API
 * facoltativa del core semplicemente ridichiarandola con `required()`.
 */
final class GoogleApis
{
    /** @var array<string, GoogleApi>|null */
    private static ?array $apis = null;

    /** @return array<string, GoogleApi> */
    public static function all(): array
    {
        if (self::$apis !== null) {
            return self::$apis;
        }

        $apis = [];
        $add = static function (iterable $items) use (&$apis): void {
            foreach ($items as $api) {
                if (!$api instanceof GoogleApi) {
                    throw new \InvalidArgumentException('Definizione API Google non valida.');
                }
                $apis[$api->key()] = isset($apis[$api->key()]) ? $apis[$api->key()]->merge($api) : $api;
            }
        };

        $add(self::core());
        foreach (Registry::enabled() as $module) {
            $entrypoint = $module->entrypoint();
            if (is_subclass_of($entrypoint, ModuleGoogleApis::class)) {
                $add($entrypoint::googleApis());
            }
        }
        $file = rtrim((string) ($GLOBALS['ROOT'] ?? ''), '/').'/custom/config/google-apis.php';
        if (is_file($file)) {
            $add(require $file);
        }

        return self::$apis = $apis;
    }

    public static function get(string $key): ?GoogleApi
    {
        return self::all()[$key] ?? null;
    }

    /** @return array<string, GoogleApi> */
    public static function required(): array
    {
        return array_filter(self::all(), static fn (GoogleApi $api): bool => $api->isRequired());
    }

    /**
     * API obbligatorie con almeno un campo vuoto. Vedi `GoogleApi::isConfigured()`
     * per quali valori passare.
     *
     * @param array<string, mixed> $credentials
     * @return array<string, GoogleApi>
     */
    public static function missing(array $credentials): array
    {
        return array_filter(self::required(), static fn (GoogleApi $api): bool => !$api->isConfigured($credentials));
    }

    public static function reset(): void
    {
        self::$apis = null;
    }

    /** @return list<GoogleApi> */
    private static function core(): array
    {
        return [
            GoogleApi::make('recaptcha_enterprise')
                ->name('reCAPTCHA Enterprise API')
                ->service('recaptchaenterprise.googleapis.com')
                ->keyType(GoogleApi::KEY_SERVER)
                ->fields(['gcp_project_id', 'gcp_api_key', 'g_recaptcha_site_key'])
                ->required()
                ->usedBy('app'),
            GoogleApi::make('maps_javascript')
                ->name('Maps JavaScript API')
                ->service('maps-backend.googleapis.com')
                ->keyType(GoogleApi::KEY_BROWSER)
                ->fields(['gcp_client_api_key'])
                ->usedBy('app'),
            GoogleApi::make('places')
                ->name('Places API (New)')
                ->service('places.googleapis.com')
                ->keyType(GoogleApi::KEY_BROWSER)
                ->fields(['gcp_client_api_key'])
                ->usedBy('app'),
            GoogleApi::make('places_sync')
                ->name('Places API (New)')
                ->service('places.googleapis.com')
                ->keyType(GoogleApi::KEY_SERVER)
                ->fields(['gcp_api_key', 'g_maps_place_id'])
                ->usedBy('app'),
            GoogleApi::make('maps_embed')
                ->name('Maps Embed API')
                ->service('maps-embed-backend.googleapis.com')
                ->keyType(GoogleApi::KEY_BROWSER)
                ->fields(['gcp_client_api_key'])
                ->usedBy('app'),
            GoogleApi::make('directions')
                ->name('Directions API')
                ->service('directions-backend.googleapis.com')
                ->keyType(GoogleApi::KEY_BROWSER)
                ->fields(['gcp_client_api_key'])
                ->usedBy('app'),
            GoogleApi::make('google_oauth')
                ->name('Google Auth Platform')
                ->keyType(GoogleApi::KEY_OAUTH)
                ->fields(['google_oauth_client_id'])
                ->usedBy('app'),
        ];
    }
}
