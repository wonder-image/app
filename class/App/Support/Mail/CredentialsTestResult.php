<?php

namespace Wonder\App\Support\Mail;

/** Esito di `CredentialsTest::run()`: testo semplice, non HTML. */
final class CredentialsTestResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly string $title,
        public readonly string $message,
        public readonly string $service,
        public readonly string $host,
        public readonly string $username,
    ) {
    }

    public static function failure(string $title, string $message, object $credentials): self
    {
        return new self(false, $title, $message, self::service($credentials), self::host($credentials), self::username($credentials));
    }

    public static function success(string $title, string $message, object $credentials): self
    {
        return new self(true, $title, $message, self::service($credentials), self::host($credentials), self::username($credentials));
    }

    public static function service(object $credentials): string
    {
        $service = strtolower(trim((string) ($credentials->service ?? 'phpmailer')));

        return in_array($service, ['phpmailer', 'brevo'], true) ? $service : 'phpmailer';
    }

    public static function host(object $credentials): string
    {
        return trim((string) ($credentials->host ?? ''));
    }

    public static function username(object $credentials): string
    {
        return trim((string) ($credentials->username ?? ''));
    }
}
