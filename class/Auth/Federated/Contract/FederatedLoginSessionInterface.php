<?php

namespace Wonder\Auth\Federated\Contract;

interface FederatedLoginSessionInterface
{
    public function loginUser(int $userId, string $area, array $meta = []): bool;
}
