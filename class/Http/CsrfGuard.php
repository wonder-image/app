<?php

namespace Wonder\Http;

use Wonder\Http\Exceptions\ForbiddenHttpException;

/**
 * Verifica CSRF delle route che la dichiarano (`->csrf()` su gruppo o route).
 *
 * Non è centrale: `RouteDispatcher` la applica solo alle route con
 * l'attributo `csrf` a `true`; le altre (OAuth, Bearer, task) restano fuori.
 */
final class CsrfGuard
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public static function requires(array $route, ?string $method = null): bool
    {
        $method = strtoupper(trim((string) ($method ?? $_SERVER['REQUEST_METHOD'] ?? 'GET')));

        return ($route['csrf'] ?? false) === true && !in_array($method, self::SAFE_METHODS, true);
    }

    /** @throws ForbiddenHttpException se la route chiede il CSRF e il token manca o non vale */
    public static function enforce(array $route, ?string $method = null): void
    {
        if (self::requires($route, $method) && !Csrf::verify()) {
            throw new ForbiddenHttpException('Token CSRF non valido.');
        }
    }
}
