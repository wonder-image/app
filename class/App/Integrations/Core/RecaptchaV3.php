<?php

namespace Wonder\App\Integrations\Core;

use Wonder\App\Integrations\Integration;

final class RecaptchaV3 extends Integration
{
    public function key(): string
    {
        return 'recaptcha-v3';
    }

    public function name(): string
    {
        return 'reCAPTCHA v3';
    }

    public function category(): string
    {
        return 'google';
    }

    public function icon(): string
    {
        return 'bi-shield-lock';
    }

    public function description(): string
    {
        return 'Punteggio in background, senza casella.';
    }

    public function fields(): array
    {
        return [
            'g_recaptcha_score_site_key' => 8,
            'g_recaptcha_badge' => 4,
        ];
    }

    public function order(): int
    {
        return 40;
    }

    public function requires(): ?string
    {
        return 'gcp';
    }

    public function modes(): array
    {
        return ['g_recaptcha_badge'];
    }

    public function guide(): string
    {
        return 'Punteggio in background, senza casella. Badge nascosto: sotto il form compare la riga di attribuzione di Google.';
    }
}
