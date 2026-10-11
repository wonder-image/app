<?php

namespace Wonder\App\Support\GooglePlaces;

use RuntimeException;
use Wonder\App\Credentials;
use Wonder\App\Support\GoogleMaps;

/**
 * Place Details (New) via HTTPS con la chiave server `GCP_API_KEY`, inviata
 * nell'header `X-Goog-Api-Key` e mai nell'URL: non finisce nei log né negli
 * errori. Il field mask chiede solo i campi usati da `PlaceMapper`.
 */
class HttpPlaceDetailsClient implements PlaceDetailsClient
{
    public const ENDPOINT = 'https://places.googleapis.com/v1/places/';

    public const FIELDS = [
        'id',
        'businessStatus',
        'regularOpeningHours',
        'regularSecondaryOpeningHours',
        'currentOpeningHours',
        'rating',
        'userRatingCount',
        'reviews',
        'googleMapsUri',
    ];

    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly int $timeout = 20
    ) {}

    public function details(string $placeId, string $language): array
    {
        $key = trim((string) ($this->apiKey ?? Credentials::api()->gcp_api_key));

        if ($key === '') {
            throw new RuntimeException('Chiave server Google (GCP_API_KEY) non configurata.');
        }

        $placeId = GoogleMaps::cleanPlaceId($placeId);

        if ($placeId === null) {
            throw new RuntimeException('Place ID non valido.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('Estensione cURL non disponibile.');
        }

        $query = preg_match('/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $language) === 1
            ? '?'.http_build_query(['languageCode' => $language])
            : '';
        $curl = curl_init(self::ENDPOINT.rawurlencode($placeId).$query);

        curl_setopt_array($curl, [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-Goog-Api-Key: '.$key,
                'X-Goog-FieldMask: '.implode(',', static::FIELDS),
                'Accept: application/json',
            ],
        ]);

        try {
            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

            if ($body === false) {
                throw new RuntimeException('Richiesta a Google fallita (cURL '.curl_errno($curl).').');
            }
        } finally {
            unset($curl);
        }

        $data = json_decode((string) $body, true);

        if ($status !== 200 || !is_array($data)) {
            $reason = is_array($data) ? trim((string) ($data['error']['status'] ?? '')) : '';

            throw new RuntimeException('Google ha risposto HTTP '.$status.($reason !== '' ? ' ('.$reason.')' : '').'.');
        }

        return $data;
    }
}
