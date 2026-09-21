<?php

namespace Wonder\Backend\Support;

/**
 * Fonte unica di verità per "l'utente backend può creare la risorsa target?".
 * Usata dal renderer (visibilità del "+") e dal controller (check server-side).
 */
final class QuickCreateAuthorizer
{
    /** @return list<string> authority di creazione backend (create, ripiego su store). */
    public static function createAuthority(string $resourceClass): array
    {
        $backend = (array) $resourceClass::permissionSchema()->get('backend');
        $authority = $backend['create'] ?? $backend['store'] ?? [];

        return array_values((array) $authority);
    }

    public static function userCanCreate(string $resourceClass, array $userAuthority): bool
    {
        $required = self::createAuthority($resourceClass);

        // Nessuna authority richiesta = aperta a ogni utente backend autenticato.
        if ($required === []) {
            return true;
        }

        return array_intersect($required, $userAuthority) !== [];
    }
}
