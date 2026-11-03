<?php

namespace Wonder\App\Integrations\Core;

use Wonder\App\Integrations\Integration;

/** Invio delle email con l'API di Brevo; la stessa chiave serve al marketing. */
final class BrevoMail extends Integration
{
    public function key(): string
    {
        return 'brevo-mail';
    }

    public function name(): string
    {
        return 'Brevo';
    }

    public function category(): string
    {
        return 'email';
    }

    public function icon(): string
    {
        return 'bi-send';
    }

    public function description(): string
    {
        return 'Invio delle email con l\'API di Brevo.';
    }

    public function fields(): array
    {
        return [
            'mail_service' => 12,
            'brevo_api_key' => 12,
        ];
    }

    public function order(): int
    {
        return 120;
    }

    public function modes(): array
    {
        return ['mail_service'];
    }

    public function note(array $values): ?string
    {
        return ($values['mail_service'] ?? 'phpmailer') === 'brevo' ? 'In uso per l\'invio' : null;
    }
}
