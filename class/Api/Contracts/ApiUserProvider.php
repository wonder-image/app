<?php

namespace Wonder\Api\Contracts;

/**
 * Risolve l'utente del claim `sub` di un token API.
 *
 * L'oggetto restituito ha la forma di `infoUser()`: `exists`, `active`,
 * `authority` e, per ogni authority API, la riga `api_users` (`exists`, `id`,
 * `token`, `active`, `allowed_ips`, `allowed_domains`).
 */
interface ApiUserProvider
{
    public function user(int|string $id): ?object;
}
