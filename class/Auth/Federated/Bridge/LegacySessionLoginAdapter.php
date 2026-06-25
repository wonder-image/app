<?php

namespace Wonder\Auth\Federated\Bridge;

use Wonder\Auth\AuthLog;
use Wonder\Auth\Federated\Contract\FederatedLoginSessionInterface;
use Wonder\Auth\RememberMe;

final class LegacySessionLoginAdapter implements FederatedLoginSessionInterface
{
    private bool $rememberMe;

    public function __construct(bool $rememberMe = true)
    {
        $this->rememberMe = $rememberMe;
    }

    public function loginUser(int $userId, string $area, array $meta = []): bool
    {
        if ($userId <= 0 || trim($area) === '') {
            return false;
        }

        $_SESSION['user_id'] = $userId;

        if ($this->rememberMe) {
            RememberMe::set($userId, $area);
        }

        AuthLog::write('login_success', $userId, $area, true, $meta + [
            'method' => 'federated',
        ]);

        return true;
    }
}
