<?php

namespace Wonder\Auth\Federated;

final class FederatedOnboardingContext
{
    public string $stage;
    public string $area;
    public ?int $userId;
    public FederatedIdentityPayload $identity;
    public array $input;

    public function __construct(
        string $stage,
        string $area,
        FederatedIdentityPayload $identity,
        ?int $userId = null,
        array $input = []
    ) {
        $this->stage = trim($stage);
        $this->area = trim($area);
        $this->identity = $identity;
        $this->userId = $userId;
        $this->input = $input;
    }
}
