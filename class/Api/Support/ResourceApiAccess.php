<?php

namespace Wonder\Api\Support;

use Wonder\Api\EndpointException;
use Wonder\App\Resource;

/**
 * Regole di accesso delle rotte `/api/resource/{slug}/`.
 *
 * - permessi `api` vuoti per l'azione = azione negata (403), non "tutte le
 *   authority API" come per gli handler legacy (`Endpoint::resolveAuth`);
 * - `apiSchema()->guard()` dichiarato = authority richiesta in più;
 * - token di pagina rifiutato salvo `apiSchema()->allowPageToken()`.
 */
final class ResourceApiAccess
{
    /**
     * Authority ammesse per l'azione.
     *
     * @return list<string>
     * @throws EndpointException 403 se la Resource non ne dichiara
     */
    public static function authorities(string $resourceClass, string $action): array
    {
        $authorities = self::declared($resourceClass, $action);

        if ($authorities === []) {
            throw new EndpointException('Azione API non consentita per questa risorsa.', 403);
        }

        return $authorities;
    }

    /**
     * Authority dichiarate in `permissionSchema()->api()`, normalizzate come
     * fa `Endpoint` (liste o chiavi di array associativi, stringhe non vuote).
     *
     * @return list<string>
     */
    public static function declared(string $resourceClass, string $action): array
    {
        if (!is_subclass_of($resourceClass, Resource::class)) {
            return [];
        }

        $permissions = (array) $resourceClass::permissionSchema()->get('api');
        $value = $permissions[$action] ?? [];
        $values = is_array($value)
            ? (array_is_list($value) ? $value : array_keys($value))
            : [$value];
        $out = [];

        foreach ($values as $authority) {
            if ((is_string($authority) || is_numeric($authority)) && trim((string) $authority) !== '') {
                $out[] = trim((string) $authority);
            }
        }

        return array_values(array_unique($out));
    }

    public static function allowsPageToken(string $resourceClass): bool
    {
        return is_subclass_of($resourceClass, Resource::class)
            && $resourceClass::apiSchema()->allowsPageToken();
    }

    /**
     * @param list<string> $owned authority dell'utente del token
     * @throws EndpointException 403 se manca il guard dichiarato
     */
    public static function requireGuard(string $resourceClass, array $owned): void
    {
        $guard = is_subclass_of($resourceClass, Resource::class)
            ? $resourceClass::apiSchema()->declaredGuard()
            : null;

        if ($guard !== null && !in_array($guard, array_map('strval', $owned), true)) {
            throw new EndpointException('Utente non autorizzato per questo endpoint!', 403);
        }
    }
}
