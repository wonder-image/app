<?php

namespace Wonder\App\Integrations\Core;

use Wonder\App\Integrations\Integration;

final class GoogleSignIn extends Integration
{
    public function key(): string
    {
        return 'google-sign-in';
    }

    public function name(): string
    {
        return 'Accedi con Google';
    }

    public function category(): string
    {
        return 'google';
    }

    public function icon(): string
    {
        return 'bi-person-badge';
    }

    public function description(): string
    {
        return 'Accesso degli utenti con l\'account Google.';
    }

    public function fields(): array
    {
        return [
            'google_oauth_client_id' => 12,
            'google_oauth_client_secret' => 12,
            'google_oauth_redirect_uri' => 12,
        ];
    }

    public function order(): int
    {
        return 60;
    }

    public function requires(): ?string
    {
        return 'gcp';
    }

    public function required(array $values): array
    {
        return ['google_oauth_client_id', 'google_oauth_client_secret'];
    }

    public function guide(): string
    {
        return 'Senza URI di reindirizzamento si usa quello predefinito del sito.';
    }
}
