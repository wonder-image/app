<?php

namespace Wonder\Auth\Federated;

use Wonder\Auth\Federated\Contract\AdditionalProfileDataHandlerInterface;
use Wonder\Auth\Federated\Contract\FederatedOnboardingDataResolverInterface;
use Wonder\Auth\Federated\Contract\PostFederatedLoginHookInterface;

final class FederatedExtensionPipeline
{
    /** @var AdditionalProfileDataHandlerInterface[] */
    private array $dataHandlers;

    /** @var PostFederatedLoginHookInterface[] */
    private array $postLoginHooks;

    /** @var FederatedOnboardingDataResolverInterface[] */
    private array $resolvers;

    public function __construct(array $dataHandlers = [], array $postLoginHooks = [], array $resolvers = [])
    {
        $this->dataHandlers = $dataHandlers;
        $this->postLoginHooks = $postLoginHooks;
        $this->resolvers = $resolvers;
    }

    public function requiredFields(FederatedOnboardingContext $context): array
    {
        $fields = [];

        foreach ($this->resolvers as $resolver) {
            if (!$resolver instanceof FederatedOnboardingDataResolverInterface) {
                continue;
            }

            $fields = array_merge($fields, $resolver->requiredFields($context));
        }

        return array_values(array_unique(array_map(static fn($field) => trim((string) $field), $fields)));
    }

    public function validate(FederatedOnboardingContext $context): FederatedValidationResult
    {
        $errors = [];

        foreach ($this->dataHandlers as $handler) {
            if (!$handler instanceof AdditionalProfileDataHandlerInterface || !$handler->supportsStage($context->stage)) {
                continue;
            }

            $result = $handler->validate($context->input, $context);
            if (!$result->valid) {
                $errors = array_merge($errors, $result->errors);
            }
        }

        if (count($errors) > 0) {
            return FederatedValidationResult::fail($errors);
        }

        return FederatedValidationResult::ok();
    }

    public function persist(FederatedOnboardingContext $context): void
    {
        if (($context->userId ?? 0) <= 0) {
            return;
        }

        foreach ($this->dataHandlers as $handler) {
            if (!$handler instanceof AdditionalProfileDataHandlerInterface || !$handler->supportsStage($context->stage)) {
                continue;
            }

            $handler->persist((int) $context->userId, $context->input, $context);
        }
    }

    public function runPostLoginHooks(int $userId, FederatedIdentityPayload $identity, FederatedAuthResult $result): void
    {
        foreach ($this->postLoginHooks as $hook) {
            if (!$hook instanceof PostFederatedLoginHookInterface) {
                continue;
            }

            $hook->execute($userId, $identity, $result);
        }
    }
}
