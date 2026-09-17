<?php

namespace Wonder\App\Support;

/**
 * Link a Google Maps dal Place ID, senza chiave API (Maps URLs).
 */
final class GoogleMapsLink
{
    public static function forPlace(string $placeId, string $query = ''): string
    {
        $placeId = trim($placeId);

        if ($placeId === '') {
            return '';
        }

        $query = trim($query);

        return 'https://www.google.com/maps/search/?'.http_build_query([
            'api' => 1,
            'query' => $query !== '' ? $query : $placeId,
            'query_place_id' => $placeId,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
