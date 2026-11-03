<?php

namespace Wonder\App\Integrations\Core;

use Wonder\App\Integrations\Integration;

/** La chiave del sito su Wonder Image: assegnata dal provisioning, non si modifica. */
final class WonderImage extends Integration
{
    public function key(): string
    {
        return 'wonder-image';
    }

    public function name(): string
    {
        return 'Wonder Image';
    }

    public function category(): string
    {
        return 'wonder';
    }

    public function icon(): string
    {
        return 'bi-stars';
    }

    public function description(): string
    {
        return 'Chiave API del sito.';
    }

    public function fields(): array
    {
        return [
            'api_key' => 12,
        ];
    }

    public function order(): int
    {
        return 10;
    }

    public function readonly(): bool
    {
        return true;
    }
}
