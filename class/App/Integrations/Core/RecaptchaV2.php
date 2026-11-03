<?php

namespace Wonder\App\Integrations\Core;

use Wonder\App\Integrations\Integration;

final class RecaptchaV2 extends Integration
{
    public function key(): string
    {
        return 'recaptcha-v2';
    }

    public function name(): string
    {
        return 'reCAPTCHA v2';
    }

    public function category(): string
    {
        return 'google';
    }

    public function icon(): string
    {
        return 'bi-shield-check';
    }

    public function description(): string
    {
        return 'Casella «Non sono un robot».';
    }

    public function fields(): array
    {
        return [
            'g_recaptcha_site_key' => 12,
        ];
    }

    public function order(): int
    {
        return 30;
    }

    public function requires(): ?string
    {
        return 'gcp';
    }

    public function guide(): string
    {
        return 'Casella «Non sono un robot». Da sola compare sempre; con la v3 compare solo quando il punteggio è basso.';
    }
}
