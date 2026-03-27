<?php

namespace Wonder\Auth\Federated;

use Wonder\Auth\AuthLog;
use Wonder\Auth\Federated\Contract\FederatedLoginSessionInterface;
use Wonder\Auth\Federated\Contract\UserAccountGatewayInterface;

final class FederatedLoginService
{
    private UserAccountGatewayInterface $users;
    private FederatedIdentityRepository $identities;
    private ?FederatedLoginSessionInterface $sessionLogin;

    public function __construct(
        UserAccountGatewayInterface $users,
        FederatedIdentityRepository $identities,
        ?FederatedLoginSessionInterface $sessionLogin = null
    ) {
        $this->users = $users;
        $this->identities = $identities;
        $this->sessionLogin = $sessionLogin;
    }

    public function authenticate(
        FederatedIdentityPayload $identity,
        string $area = 'frontend',
        array $requiredAuthorities = []
    ): FederatedAuthResult {
        if (!$identity->isValid()) {
            return $this->blocked('federated_login_blocked', 'invalid_federated_identity_payload', null, $area);
        }

        $linked = $this->identities->findByProviderIdentity($identity->provider, $identity->providerUserId);
        if (is_array($linked)) {
            $userId = (int) ($linked['user_id'] ?? 0);

            return $this->loginLinkedUser($userId, $identity, $area, $requiredAuthorities, 'login_success_existing_link');
        }

        if ($identity->email === '') {
            return $this->blocked('federated_login_blocked', 'federated_email_missing', null, $area);
        }

        $existingUser = $this->users->findUserByEmail($identity->email);
        if (is_array($existingUser)) {
            $userId = (int) ($existingUser['id'] ?? 0);

            try {
                $this->identities->linkIdentity($userId, $identity);
            } catch (\RuntimeException $exception) {
                return $this->blocked('federated_login_blocked', (string) $exception->getMessage(), $userId, $area);
            }

            return $this->loginLinkedUser($userId, $identity, $area, $requiredAuthorities, 'login_success_linked_existing_email');
        }

        $userId = $this->users->createUserFromFederatedIdentity($identity, $area);
        if ($userId <= 0) {
            return $this->blocked('federated_login_blocked', 'federated_user_creation_failed', null, $area);
        }

        try {
            $this->identities->linkIdentity($userId, $identity);
        } catch (\RuntimeException $exception) {
            return $this->blocked('federated_login_blocked', (string) $exception->getMessage(), $userId, $area);
        }

        return $this->loginLinkedUser($userId, $identity, $area, $requiredAuthorities, 'login_success_created_user');
    }

    private function loginLinkedUser(
        int $userId,
        FederatedIdentityPayload $identity,
        string $area,
        array $requiredAuthorities,
        string $successStatus
    ): FederatedAuthResult {
        if ($userId <= 0) {
            return $this->blocked('federated_login_blocked', 'invalid_linked_user_id', null, $area);
        }

        if (!$this->users->canAccessArea($userId, $area, $requiredAuthorities)) {
            return $this->blocked('federated_login_blocked', 'federated_user_not_allowed_in_area', $userId, $area);
        }

        if ($this->sessionLogin !== null) {
            $ok = $this->sessionLogin->loginUser($userId, $area, [
                'provider' => $identity->provider,
                'provider_user_id' => $identity->providerUserId,
            ]);

            if (!$ok) {
                return $this->blocked('federated_login_blocked', 'federated_session_login_failed', $userId, $area);
            }
        }

        AuthLog::write('federated_login_success', $userId, $area, true, [
            'provider' => $identity->provider,
            'provider_user_id' => $identity->providerUserId,
            'email' => $identity->email,
            'status' => $successStatus,
        ]);

        return FederatedAuthResult::success($successStatus, $userId, [
            'provider' => $identity->provider,
            'provider_user_id' => $identity->providerUserId,
            'email' => $identity->email,
        ]);
    }

    private function blocked(string $status, string $reason, ?int $userId = null, string $area = 'frontend'): FederatedAuthResult
    {
        AuthLog::write('federated_login_failed', $userId, $area, false, [
            'reason' => $reason,
        ]);

        return FederatedAuthResult::blocked($status, $reason, $userId);
    }
}
