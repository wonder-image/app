<?php

namespace Wonder\App\Integrations\Core;

use Wonder\App\Integrations\Integration;

/** Server di posta in uscita. `mail_service` sceglie se l'invio passa da qui o da Brevo. */
final class Smtp extends Integration
{
    public function key(): string
    {
        return 'smtp';
    }

    public function name(): string
    {
        return 'Server SMTP';
    }

    public function category(): string
    {
        return 'email';
    }

    public function icon(): string
    {
        return 'bi-envelope';
    }

    public function description(): string
    {
        return 'Invio delle email dal server di posta.';
    }

    public function fields(): array
    {
        return [
            'mail_service' => 12,
            'mail_host' => 8,
            'mail_port' => 4,
            'mail_username' => 6,
            'mail_password' => 6,
        ];
    }

    public function order(): int
    {
        return 110;
    }

    public function modes(): array
    {
        return ['mail_service'];
    }

    public function note(array $values): ?string
    {
        return ($values['mail_service'] ?? 'phpmailer') === 'phpmailer' ? 'In uso per l\'invio' : null;
    }
}
