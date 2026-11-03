<?php

namespace Wonder\App\Integrations\Core;

use Wonder\App\Integrations\Integration;

/** Place ID della sede e Map ID dello stile; il secondo è facoltativo. */
final class GoogleMaps extends Integration
{
    public function key(): string
    {
        return 'google-maps';
    }

    public function name(): string
    {
        return 'Google Maps';
    }

    public function category(): string
    {
        return 'google';
    }

    public function icon(): string
    {
        return 'bi-geo-alt';
    }

    public function description(): string
    {
        return 'Mappe, recensioni e orari della sede.';
    }

    public function fields(): array
    {
        return [
            'g_maps_place_id' => 6,
            'g_maps_map_id' => 6,
        ];
    }

    public function order(): int
    {
        return 50;
    }

    public function requires(): ?string
    {
        return 'gcp';
    }

    public function required(array $values): array
    {
        return ['g_maps_place_id'];
    }
}
