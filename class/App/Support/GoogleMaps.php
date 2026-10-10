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

    /**
     * Place ID e coordinate scritti nei campi nascosti dall'autocompletamento
     * (`places.js` della lib): un valore non valido diventa null, senza errori,
     * e le coordinate valgono solo in coppia.
     *
     * @return array<string, string|float|null> `<prefisso>place_id`, `<prefisso>lat`, `<prefisso>lng`
     */
    public static function cleanGeo(array $input, string $prefix = ''): array
    {
        $lat = self::cleanLat($input[$prefix.'lat'] ?? null);
        $lng = self::cleanLng($input[$prefix.'lng'] ?? null);

        if ($lat === null || $lng === null) {
            $lat = $lng = null;
        }

        return [
            $prefix.'place_id' => self::cleanPlaceId($input[$prefix.'place_id'] ?? null),
            $prefix.'lat' => $lat,
            $prefix.'lng' => $lng,
        ];
    }

    public static function cleanPlaceId(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return preg_match('/^[A-Za-z0-9_-]{10,255}$/', $value) === 1 ? $value : null;
    }

    public static function cleanLat(mixed $value): ?float
    {
        return self::cleanCoord($value, 90);
    }

    public static function cleanLng(mixed $value): ?float
    {
        return self::cleanCoord($value, 180);
    }

    private static function cleanCoord(mixed $value, int $limit): ?float
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ((!is_string($value) && !is_int($value) && !is_float($value)) || !is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        return is_finite($value) && abs($value) <= $limit ? $value : null;
    }
}
