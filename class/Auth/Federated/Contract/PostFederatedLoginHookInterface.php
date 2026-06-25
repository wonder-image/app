<?php

namespace Wonder\Auth\Federated\Contract;

use Wonder\Auth\Federated\FederatedAuthResult;
use Wonder\Auth\Federated\FederatedIdentityPayload;

interface PostFederatedLoginHookInterface
{
    public function execute(int $userId, FederatedIdentityPayload $identity, FederatedAuthResult $result): void;
}
