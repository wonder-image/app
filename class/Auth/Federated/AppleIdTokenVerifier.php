<?php

namespace Wonder\Auth\Federated;

use Wonder\Auth\Federated\Contract\FederatedIdTokenVerifierInterface;

final class AppleIdTokenVerifier implements FederatedIdTokenVerifierInterface
{
    public function __construct(
        private readonly string $clientId,
        private readonly ?string $nonce = null,
    ) {}

    public function provider(): string
    {
        return FederatedProvider::APPLE;
    }

    public function verify(string $idToken): FederatedIdentityPayload
    {
        $claims = (new OidcIdTokenVerifier(
            $this->clientId,
            ['https://appleid.apple.com'],
            'https://appleid.apple.com/auth/keys',
            $this->nonce,
        ))->claims($idToken);

        return FederatedClaimMapper::fromAppleClaims($claims);
    }
}
