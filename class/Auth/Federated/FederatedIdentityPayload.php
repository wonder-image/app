<?php

namespace Wonder\Auth\Federated;

final class FederatedIdentityPayload
{
    public string $provider;
    public string $providerUserId;
    public string $email;
    public bool $emailVerified;
    public string $name;
    public string $surname;
    public array $rawClaims;

    public function __construct(
        string $provider,
        string $providerUserId,
        string $email = '',
        bool $emailVerified = false,
        string $name = '',
        string $surname = '',
        array $rawClaims = []
    ) {
        $this->provider = FederatedProvider::normalize($provider);
        $this->providerUserId = trim($providerUserId);
        $this->email = strtolower(trim($email));
        $this->emailVerified = $emailVerified;
        $this->name = trim($name);
        $this->surname = trim($surname);
        $this->rawClaims = $rawClaims;
    }

    public function isValid(): bool
    {
        if (!FederatedProvider::isSupported($this->provider)) {
            return false;
        }

        return $this->providerUserId !== '';
    }
}
