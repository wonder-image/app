<?php

namespace Wonder\Auth\Federated\Contract;

use Wonder\Auth\Federated\FederatedIdentityPayload;

interface FederatedIdTokenVerifierInterface
{
    public function provider(): string;

    public function verify(string $idToken): FederatedIdentityPayload;
}
