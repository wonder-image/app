<?php

namespace Wonder\App\Support;

use Wonder\App\Credentials;

/**
 * Accesso centralizzato alle credenziali Google Maps per pagine e componenti.
 *
 * Lato JS la lib wonder-image espone `MapManager` / `MapNavigator` su window e
 * il loader `requireGoogleMaps()`: la chiave arriva dalla costante JS
 * `GOOGLE_API_KEY` emessa dagli head layout, mentre il Map ID va passato
 * esplicitamente alle opzioni di `MapManager` — `mapOptions()` prepara
 * quell'array pronto per l'encoding JSON.
 */
final class GoogleMaps
{
    public static function apiKey(): string
    {
        return trim((string) Credentials::api()->gcp_client_api_key);
    }

    public static function mapId(): string
    {
        return trim((string) Credentials::api()->g_maps_map_id);
    }

    public static function enabled(): bool
    {
        return self::apiKey() !== '';
    }

    /**
     * Opzioni per `new MapManager(element, options)` della lib wonder-image.
     * Il mapId e' incluso solo se configurato; $overrides ha la precedenza.
     */
    public static function mapOptions(array $overrides = []): array
    {
        $options = [];

        if (self::mapId() !== '') {
            $options['mapId'] = self::mapId();
        }

        return array_merge($options, $overrides);
    }
}
