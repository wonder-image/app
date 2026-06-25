<?php

namespace Wonder\Auth\Federated\Contract;

use Wonder\Auth\Federated\FederatedOnboardingContext;
use Wonder\Auth\Federated\FederatedValidationResult;

interface AdditionalProfileDataHandlerInterface
{
    public function supportsStage(string $stage): bool;

    public function validate(array $input, FederatedOnboardingContext $context): FederatedValidationResult;

    public function persist(int $userId, array $input, FederatedOnboardingContext $context): void;
}
