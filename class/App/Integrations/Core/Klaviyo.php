<?php

namespace Wonder\App\Integrations\Core;

use Wonder\App\Integrations\Integration;

final class Klaviyo extends Integration
{
    public function key(): string
    {
        return 'klaviyo';
    }

    public function name(): string
    {
        return 'Klaviyo';
    }

    public function category(): string
    {
        return 'marketing';
    }

    public function icon(): string
    {
        return 'bi-graph-up-arrow';
    }

    public function description(): string
    {
        return 'Profili ed eventi per le campagne.';
    }

    public function fields(): array
    {
        return [
            'klaviyo_api_key' => 12,
        ];
    }

    public function order(): int
    {
        return 140;
    }

    public function docsUrl(): ?string
    {
        return 'https://developers.klaviyo.com/en/reference/api_overview';
    }
}
