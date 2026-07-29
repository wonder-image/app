<?php

namespace Wonder\Elements\Media;

use InvalidArgumentException;
use JsonException;

class GoogleMap extends Media
{
    private const MAP_TYPES = ['roadmap', 'satellite', 'hybrid', 'terrain'];
    private const TRAVEL_MODES = ['DRIVING', 'BICYCLING', 'WALKING', 'TRANSIT'];

    public function __construct(array $markers = [])
    {
        $this->markers($markers)
            ->zoom(15)
            ->width('100%')
            ->height(420)
            ->mapType('roadmap')
            ->labels()
            ->travelMode()
            ->mapOptions(['clickableIcons' => false])
            ->fitBounds(['padding' => 40, 'maxZoom' => 15]);
    }

    public static function make(array $markers = []): self
    {
        return new self($markers);
    }

    public static function fromGeoJson(array|string $geoJson): self
    {
        return (new self())->geoJson($geoJson);
    }

    public function geoJson(array|string $geoJson): self
    {
        if (is_string($geoJson)) {
            try {
                $decoded = json_decode($geoJson, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException(
                    'GeoJSON della mappa non valido: ' . $exception->getMessage(),
                    0,
                    $exception
                );
            }

            if (!is_array($decoded)) {
                throw new InvalidArgumentException('Il GeoJSON della mappa deve essere un oggetto o un array.');
            }

            $geoJson = $decoded;
        }

        $features = $this->extractFeatures($geoJson);
        $markers = [];

        foreach ($features as $feature) {
            if (!is_array($feature)) {
                continue;
            }

            $geometry = $feature['geometry'] ?? null;
            if (!is_array($geometry) || ($geometry['type'] ?? null) !== 'Point') {
                continue;
            }

            $coordinates = $geometry['coordinates'] ?? null;
            if (!is_array($coordinates) || count($coordinates) < 2) {
                continue;
            }

            $properties = is_array($feature['properties'] ?? null)
                ? $feature['properties']
                : [];

            $markers[] = $this->normalizeMarker([
                'lat' => $coordinates[1],
                'lng' => $coordinates[0],
                'title' => (string) ($properties['title'] ?? $properties['name'] ?? ''),
                'properties' => $properties,
            ]);
        }

        return $this->schema('markers', $markers);
    }

    public function markers(array $markers): self
    {
        $normalized = [];

        foreach ($markers as $marker) {
            if (!is_array($marker)) {
                throw new InvalidArgumentException('Ogni marker deve essere un array.');
            }

            $normalized[] = $this->normalizeMarker($marker);
        }

        return $this->schema('markers', $normalized);
    }

    public function marker(
        int|float|string $lat,
        int|float|string $lng,
        string $title = '',
        array $properties = []
    ): self {
        $markers = $this->getSchema('markers') ?? [];
        $markers[] = $this->normalizeMarker([
            'lat' => $lat,
            'lng' => $lng,
            'title' => $title,
            'properties' => $properties,
        ]);

        return $this->schema('markers', $markers);
    }

    public function center(int|float|string $lat, int|float|string $lng): self
    {
        return $this->schema('center', $this->normalizePoint($lat, $lng));
    }

    public function zoom(int $zoom): self
    {
        if ($zoom < 0 || $zoom > 24) {
            throw new InvalidArgumentException('Lo zoom della mappa deve essere compreso tra 0 e 24.');
        }

        return $this->schema('zoom', $zoom);
    }

    public function width(int|string $width): self
    {
        $width = $this->normalizeDimension($width, 'larghezza');

        return $this->schema('width', $width)->style('width', $width);
    }

    public function height(int|string $height): self
    {
        $height = $this->normalizeDimension($height, 'altezza');

        return $this->schema('height', $height)->style('height', $height);
    }

    public function apiKey(string $apiKey): self
    {
        return $this->schema('api_key', trim($apiKey));
    }

    public function mapId(string $mapId): self
    {
        return $this->schema('map_id', trim($mapId));
    }

    public function mapType(string $mapType): self
    {
        $mapType = strtolower(trim($mapType));

        if (!in_array($mapType, self::MAP_TYPES, true)) {
            throw new InvalidArgumentException(
                "Tipo mappa {$mapType} non valido. Valori ammessi: " . implode(', ', self::MAP_TYPES)
            );
        }

        return $this->schema('map_type', $mapType);
    }

    public function labels(bool $visible = true): self
    {
        return $this->schema('labels_visible', $visible);
    }

    public function travelMode(string $travelMode = 'DRIVING'): self
    {
        $travelMode = strtoupper(trim($travelMode));

        if (!in_array($travelMode, self::TRAVEL_MODES, true)) {
            throw new InvalidArgumentException(
                "Modalità di viaggio {$travelMode} non valida. Valori ammessi: "
                . implode(', ', self::TRAVEL_MODES)
            );
        }

        return $this->schema('travel_mode', $travelMode);
    }

    public function mapOptions(array $options): self
    {
        return $this->schema('map_options', $options);
    }

    public function mergeMapOptions(array $options): self
    {
        $current = $this->getSchema('map_options') ?? [];

        return $this->schema('map_options', array_replace_recursive($current, $options));
    }

    public function fitBounds(array|bool $options = true): self
    {
        if ($options === false) {
            return $this->schema('fit_bounds', false);
        }

        return $this->schema('fit_bounds', $options === true ? [] : $options);
    }

    public function markerRenderer(?string $globalPath): self
    {
        $globalPath = trim((string) $globalPath);

        if ($globalPath !== ''
            && preg_match('/^(?:[A-Za-z_$][A-Za-z0-9_$]*)(?:\.[A-Za-z_$][A-Za-z0-9_$]*)*$/', $globalPath) !== 1
        ) {
            throw new InvalidArgumentException(
                'Il renderer dei marker deve essere il percorso di una funzione globale, '
                . 'per esempio ImmobiliMaps.markerContent.'
            );
        }

        return $this->schema('marker_renderer', $globalPath !== '' ? $globalPath : null);
    }

    public function highlightMarkers(bool $highlight = true): self
    {
        return $this->schema('highlight_markers', $highlight);
    }

    public function route(array $points): self
    {
        if ($points !== [] && count($points) < 2) {
            throw new InvalidArgumentException('Un percorso Google Maps deve contenere almeno due punti.');
        }

        $normalized = [];

        foreach ($points as $point) {
            if (!is_array($point)) {
                throw new InvalidArgumentException('Ogni punto del percorso deve essere un array.');
            }

            $normalized[] = $this->normalizePoint(
                $point['lat'] ?? null,
                $point['lng'] ?? null
            );
        }

        return $this->schema('route', $normalized);
    }

    public function navigation(bool $enabled = true, bool $autoStart = false): self
    {
        return $this->schema('navigation', [
            'enabled' => $enabled,
            'auto_start' => $enabled && $autoStart,
        ]);
    }

    public function config(): array
    {
        return [
            'apiKey' => $this->getSchema('api_key'),
            'mapId' => $this->getSchema('map_id'),
            'markers' => $this->getSchema('markers') ?? [],
            'route' => $this->getSchema('route') ?? [],
            'center' => $this->getSchema('center'),
            'zoom' => $this->getSchema('zoom') ?? 15,
            'mapType' => $this->getSchema('map_type') ?? 'roadmap',
            'labelsVisible' => $this->getSchema('labels_visible') ?? true,
            'travelMode' => $this->getSchema('travel_mode') ?? 'DRIVING',
            'mapOptions' => $this->getSchema('map_options') ?? [],
            'fitBounds' => $this->getSchema('fit_bounds') ?? false,
            'markerRenderer' => $this->getSchema('marker_renderer'),
            'highlightMarkers' => $this->getSchema('highlight_markers') ?? false,
            'navigation' => $this->getSchema('navigation') ?? [
                'enabled' => false,
                'auto_start' => false,
            ],
        ];
    }

    private function extractFeatures(array $geoJson): array
    {
        if (($geoJson['type'] ?? null) === 'FeatureCollection') {
            return is_array($geoJson['features'] ?? null) ? $geoJson['features'] : [];
        }

        if (($geoJson['type'] ?? null) === 'Feature') {
            return [$geoJson];
        }

        return array_is_list($geoJson) ? $geoJson : [];
    }

    private function normalizeMarker(array $marker): array
    {
        $point = $this->normalizePoint($marker['lat'] ?? null, $marker['lng'] ?? null);

        return [
            'lat' => $point['lat'],
            'lng' => $point['lng'],
            'title' => trim((string) ($marker['title'] ?? '')),
            'properties' => is_array($marker['properties'] ?? null) ? $marker['properties'] : [],
        ];
    }

    private function normalizePoint(mixed $lat, mixed $lng): array
    {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            throw new InvalidArgumentException('Le coordinate della mappa devono essere numeriche.');
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if (!is_finite($lat) || $lat < -90 || $lat > 90) {
            throw new InvalidArgumentException('La latitudine deve essere compresa tra -90 e 90.');
        }

        if (!is_finite($lng) || $lng < -180 || $lng > 180) {
            throw new InvalidArgumentException('La longitudine deve essere compresa tra -180 e 180.');
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    private function normalizeDimension(int|string $value, string $label): string
    {
        if (is_int($value)) {
            if ($value <= 0) {
                throw new InvalidArgumentException("La {$label} deve essere maggiore di zero.");
            }

            return $value . 'px';
        }

        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException("La {$label} non può essere vuota.");
        }

        return $value;
    }
}
