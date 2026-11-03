<?php

namespace Wonder\App\Integrations\Core;

use Wonder\App\Integrations\Integration;

final class FattureInCloud extends Integration
{
    public function key(): string
    {
        return 'fatture-in-cloud';
    }

    public function name(): string
    {
        return 'Fatture in Cloud';
    }

    public function category(): string
    {
        return 'invoicing';
    }

    public function icon(): string
    {
        return 'bi-receipt';
    }

    public function description(): string
    {
        return 'Emissione delle fatture elettroniche.';
    }

    public function fields(): array
    {
        return [
            'fatture_in_cloud_company_id' => 6,
            'fatture_in_cloud_token' => 6,
        ];
    }

    public function order(): int
    {
        return 150;
    }

    public function docsUrl(): ?string
    {
        return 'https://wonder-image.gitbook.io/app/altro/servizi/fatture-in-cloud';
    }
}
