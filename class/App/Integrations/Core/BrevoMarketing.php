<?php

namespace Wonder\App\Integrations\Core;

use Wonder\App\Integrations\Integration;

/** Liste e contatti su Brevo: usa la stessa chiave dell'invio. */
final class BrevoMarketing extends Integration
{
    public function key(): string
    {
        return 'brevo';
    }

    public function name(): string
    {
        return 'Brevo';
    }

    public function category(): string
    {
        return 'marketing';
    }

    public function icon(): string
    {
        return 'bi-megaphone';
    }

    public function description(): string
    {
        return 'Liste e contatti per le campagne.';
    }

    public function fields(): array
    {
        return [
            'brevo_api_key' => 12,
        ];
    }

    public function order(): int
    {
        return 130;
    }

    public function guide(): string
    {
        return 'La chiave è la stessa dell\'invio con Brevo: cambiarla qui la cambia anche lì.';
    }
}
