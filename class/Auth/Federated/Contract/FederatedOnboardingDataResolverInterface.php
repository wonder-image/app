<?php

namespace Wonder\Auth\Federated\Contract;

use Wonder\Auth\Federated\FederatedOnboardingContext;

interface FederatedOnboardingDataResolverInterface
{
    public function requiredFields(FederatedOnboardingContext $context): array;
}
