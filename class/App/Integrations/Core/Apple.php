<?php

namespace Wonder\App\Integrations\Core;

use Wonder\App\Integrations\Integration;

final class Apple extends Integration
{
    public function key(): string
    {
        return 'apple';
    }

    public function name(): string
    {
        return 'Accedi con Apple';
    }

    public function category(): string
    {
        return 'access';
    }

    public function icon(): string
    {
        return 'bi-apple';
    }

    public function description(): string
    {
        return 'Accesso degli utenti con l\'ID Apple.';
    }

    public function fields(): array
    {
        return [
            'apple_oauth_client_id' => 6,
            'apple_oauth_team_id' => 6,
            'apple_oauth_key_id' => 6,
            'apple_oauth_private_key' => 6,
            'apple_oauth_redirect_uri' => 12,
        ];
    }

    public function order(): int
    {
        return 70;
    }

    public function variant(): string
    {
        return 'dark';
    }

    public function required(array $values): array
    {
        return ['apple_oauth_client_id', 'apple_oauth_team_id', 'apple_oauth_key_id', 'apple_oauth_private_key'];
    }

    public function guide(): string
    {
        return 'Senza URI di reindirizzamento si usa quello predefinito del sito.';
    }
}
