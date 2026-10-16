<?php

namespace Wonder\App\Support;

use InvalidArgumentException;

/**
 * URL della Maps Embed API (`/maps/embed/v1/place`) per un iframe.
 *
 * Usa la chiave browser (`GCP_CLIENT_API_KEY`, la stessa di `GoogleMaps`):
 * la Embed API deve essere abilitata per quella chiave. Senza chiave o senza
 * luogo restituisce una stringa vuota, così il chiamante decide cosa mostrare.
 * La Embed API accetta solo le viste `roadmap` e `satellite`.
 */
final class GoogleMapsEmbed
{
    public const MAP_TYPES = ['roadmap', 'satellite'];

    public static function forPlace(string $placeId, string $mapType = 'roadmap', ?int $zoom = null, string $language = '', ?string $key = null): string
    {
        $placeId = GoogleMaps::cleanPlaceId($placeId);

        return $placeId === null ? '' : self::url('place_id:'.$placeId, $mapType, $zoom, $language, $key);
    }

    public static function forCoordinates(mixed $lat, mixed $lng, string $mapType = 'roadmap', ?int $zoom = null, string $language = '', ?string $key = null): string
    {
        $lat = GoogleMaps::cleanLat($lat);
        $lng = GoogleMaps::cleanLng($lng);

        return $lat === null || $lng === null ? '' : self::url($lat.','.$lng, $mapType, $zoom, $language, $key);
    }

    /** Sede di `SocietyLocations` (oggetto o array): place_id, altrimenti coordinate. */
    public static function forLocation(object|array $location, string $mapType = 'roadmap', ?int $zoom = null, string $language = '', ?string $key = null): string
    {
        $location = (array) $location;
        $url = self::forPlace((string) ($location['google_place_id'] ?? ''), $mapType, $zoom, $language, $key);

        return $url !== '' ? $url : self::forCoordinates($location['lat'] ?? null, $location['lng'] ?? null, $mapType, $zoom, $language, $key);
    }

    public static function mapType(string $mapType): string
    {
        $mapType = strtolower(trim($mapType));

        if (!in_array($mapType, self::MAP_TYPES, true)) {
            throw new InvalidArgumentException('Vista mappa non valida: usa '.implode(' o ', self::MAP_TYPES).'.');
        }

        return $mapType;
    }

    private static function url(string $query, string $mapType, ?int $zoom, string $language, ?string $key): string
    {
        $mapType = self::mapType($mapType);
        $key = trim($key ?? GoogleMaps::apiKey());

        if ($key === '') {
            return '';
        }

        if ($zoom !== null && ($zoom < 0 || $zoom > 21)) {
            throw new InvalidArgumentException('Lo zoom della mappa deve essere tra 0 e 21.');
        }

        $language = trim($language);

        if ($language !== '' && preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $language) !== 1) {
            throw new InvalidArgumentException('Lingua mappa non valida (es. it, en-GB).');
        }

        return 'https://www.google.com/maps/embed/v1/place?'.http_build_query(array_filter([
            'key' => $key,
            'q' => $query,
            'maptype' => $mapType,
            'zoom' => $zoom,
            'language' => $language,
        ], static fn (mixed $value): bool => $value !== null && $value !== ''), '', '&', PHP_QUERY_RFC3986);
    }
}
