<?php

namespace Wonder\Api\Token;

use Wonder\Api\Contracts\ApiUserProvider;

/** Provider predefinito: l'helper legacy `infoUser()`. */
final class InfoUserProvider implements ApiUserProvider
{
    public function user(int|string $id): ?object
    {
        $user = \infoUser($id);

        return is_object($user) ? $user : null;
    }
}
