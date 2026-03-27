<?php

namespace Wonder\Auth\Federated;

use Wonder\Auth\Federated\Contract\FederatedIdTokenVerifierInterface;

final class GoogleIdTokenVerifierStub implements FederatedIdTokenVerifierInterface
{
    public function verify(string $idToken): FederatedIdentityPayload
    {
        $idToken = trim($idToken);
        if ($idToken === '') {
            throw new \InvalidArgumentException('google_id_token_missing');
        }

        throw new \RuntimeException('google_id_token_verification_not_implemented');
    }

    public function provider(): string
    {
        return FederatedProvider::GOOGLE;
    }
}
