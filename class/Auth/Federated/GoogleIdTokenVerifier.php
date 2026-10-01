<?php

namespace Wonder\Auth\Federated;

use Wonder\Auth\Federated\Contract\FederatedIdTokenVerifierInterface;

final class GoogleIdTokenVerifier implements FederatedIdTokenVerifierInterface
{
    public function __construct(
        private readonly string $clientId,
        private readonly ?string $nonce = null,
    ) {}

    public function provider(): string
    {
        return FederatedProvider::GOOGLE;
    }

    public function verify(string $idToken): FederatedIdentityPayload
    {
        $claims = (new OidcIdTokenVerifier(
            $this->clientId,
            ['https://accounts.google.com', 'accounts.google.com'],
            'https://www.googleapis.com/oauth2/v3/certs',
            $this->nonce,
        ))->claims($idToken);

        return FederatedClaimMapper::fromGoogleClaims($claims);
    }
}
