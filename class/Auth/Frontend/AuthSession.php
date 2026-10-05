<?php

namespace Wonder\Auth\Frontend;

final class AuthSession
{
    private const KEY = 'wonder_auth_csrf';

    public static function queueEvent(string $event, string $method): void
    {
        if (in_array($event, ['login', 'sign_up'], true)) {
            $_SESSION['wonder_auth_events'][] = ['event' => $event, 'method' => $method];
        }
    }

    public static function consumeEvents(): array
    {
        $events = (array) ($_SESSION['wonder_auth_events'] ?? []);
        unset($_SESSION['wonder_auth_events']);
        return $events;
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::KEY];
    }

    public static function verify(mixed $token): bool
    {
        $stored = (string) ($_SESSION[self::KEY] ?? '');
        $token = (string) $token;

        return $stored !== '' && $token !== '' && hash_equals($stored, $token);
    }
}
