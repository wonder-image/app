<?php

namespace Wonder\OAuth;

/** Chiamante autenticato da un access token OAuth. */
final readonly class OAuthCaller
{
    public function __construct(
        public object $user,
        public int $userId,
        public int $grantId,
        public string $clientId,
        public string $resource,
    ) {}
}
