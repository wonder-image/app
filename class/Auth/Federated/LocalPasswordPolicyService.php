<?php

namespace Wonder\Auth\Federated;

use Wonder\Auth\Federated\Contract\UserAccountGatewayInterface;

final class LocalPasswordPolicyService
{
    private UserAccountGatewayInterface $users;
    private FederatedIdentityRepository $identities;

    public function __construct(UserAccountGatewayInterface $users, FederatedIdentityRepository $identities)
    {
        $this->users = $users;
        $this->identities = $identities;
    }

    public function evaluateEmailPasswordLogin(string $email, string $passwordSetUrl = ''): ?FederatedAuthResult
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $user = $this->users->findUserByEmail($email);
        if (!is_array($user)) {
            return null;
        }

        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        if ($this->users->hasLocalPassword($userId)) {
            return null;
        }

        $linked = $this->identities->findByUserId($userId);
        if (count($linked) === 0) {
            return null;
        }

        return FederatedAuthResult::blocked(
            'email_password_login_blocked',
            'local_password_missing_for_federated_account',
            $userId,
            $passwordSetUrl,
            [
                'providers' => array_values(array_unique(array_map(static fn($row) => (string) ($row['provider'] ?? ''), $linked))),
            ]
        );
    }

    public function setLocalPassword(int $userId, string $plainPassword): FederatedAuthResult
    {
        if ($userId <= 0) {
            return FederatedAuthResult::blocked('set_password_failed', 'invalid_user_id');
        }

        $plainPassword = trim($plainPassword);
        if ($plainPassword === '') {
            return FederatedAuthResult::blocked('set_password_failed', 'password_empty', $userId);
        }

        $hash = \hashPassword($plainPassword);
        $this->users->setLocalPassword($userId, $hash);

        return FederatedAuthResult::success('set_password_success', $userId);
    }
}
