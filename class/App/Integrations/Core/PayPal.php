<?php

namespace Wonder\App\Integrations\Core;

use Wonder\App\Integrations\Integration;

final class PayPal extends Integration
{
    public function key(): string
    {
        return 'paypal';
    }

    public function name(): string
    {
        return 'PayPal';
    }

    public function category(): string
    {
        return 'payments';
    }

    public function icon(): string
    {
        return 'bi-paypal';
    }

    public function description(): string
    {
        return 'Pagamenti con PayPal.';
    }

    public function fields(): array
    {
        return [
            'paypal_live' => 12,
            'paypal_client_id' => 6,
            'paypal_client_secret' => 6,
        ];
    }

    public function order(): int
    {
        return 90;
    }

    public function modes(): array
    {
        return ['paypal_live'];
    }

    public function testMode(array $values): bool
    {
        return !filter_var($values['paypal_live'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
