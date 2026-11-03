<?php

namespace Wonder\App\Integrations\Core;

use Wonder\App\Integrations\Integration;

/** XPay Web/Global con l'API Key, oppure XPay classico con alias e chiave MAC. */
final class Nexi extends Integration
{
    public function key(): string
    {
        return 'nexi';
    }

    public function name(): string
    {
        return 'Nexi';
    }

    public function category(): string
    {
        return 'payments';
    }

    public function icon(): string
    {
        return 'bi-credit-card';
    }

    public function description(): string
    {
        return 'Pagamenti con carta tramite XPay.';
    }

    public function fields(): array
    {
        return [
            'nexi_prod' => 12,
            'nexi_api_key' => 12,
            'nexi_alias' => 6,
            'nexi_mac_key' => 6,
        ];
    }

    public function order(): int
    {
        return 100;
    }

    public function modes(): array
    {
        return ['nexi_prod'];
    }

    public function required(array $values): array
    {
        if (self::filled($values['nexi_api_key'] ?? null)) {
            return ['nexi_api_key'];
        }

        return self::filled($values['nexi_alias'] ?? null) || self::filled($values['nexi_mac_key'] ?? null)
            ? ['nexi_alias', 'nexi_mac_key']
            : ['nexi_api_key'];
    }

    public function testMode(array $values): bool
    {
        return !filter_var($values['nexi_prod'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public function guide(): string
    {
        return 'Compila l\'API Key per XPay Web/Global oppure alias e chiave MAC per XPay classico.';
    }
}
