<?php

namespace Wonder\Auth\Frontend;

use Wonder\Http\Csrf;

final class AuthSession
{
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
        return Csrf::token();
    }

    public static function verify(mixed $token): bool
    {
        return Csrf::verify((string) $token);
    }
}
