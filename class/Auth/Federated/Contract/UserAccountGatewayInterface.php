<?php

namespace Wonder\Auth\Federated\Contract;

use Wonder\Auth\Federated\FederatedIdentityPayload;

interface UserAccountGatewayInterface
{
    public function findUserByEmail(string $email): ?array;

    public function findUserById(int $userId): ?array;

    public function createUserFromFederatedIdentity(FederatedIdentityPayload $identity, string $area): int;

    public function hasLocalPassword(int $userId): bool;

    public function setLocalPassword(int $userId, string $passwordHash): void;

    public function canAccessArea(int $userId, string $area, array $requiredAuthorities = []): bool;
}
