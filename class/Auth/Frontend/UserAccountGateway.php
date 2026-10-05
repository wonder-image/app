<?php

namespace Wonder\Auth\Frontend;

use Wonder\App\Models\User\User;
use Wonder\Auth\Federated\Contract\UserAccountGatewayInterface;
use Wonder\Auth\Federated\FederatedIdentityPayload;

class UserAccountGateway implements UserAccountGatewayInterface
{
    public function __construct(private readonly array $authorities = ['client']) {}

    public function findUserByEmail(string $email): ?array
    {
        return $this->fromUser(\infoUser(strtolower(trim($email)), 'email'));
    }

    public function findUserById(int $userId): ?array
    {
        return $this->fromUser(\infoUser($userId, 'id'));
    }

    public function createUserFromFederatedIdentity(FederatedIdentityPayload $identity, string $area): int
    {
        if (!$identity->emailVerified || $identity->email === '') {
            return 0;
        }

        $usernameBase = explode('@', $identity->email)[0];
        $created = User::create([
            'name' => $identity->name,
            'surname' => $identity->surname,
            'email' => $identity->email,
            'username' => \create_link($usernameBase, 'user', 'username'),
            'authority' => json_encode($this->authorities, JSON_THROW_ON_ERROR),
            'area' => json_encode([$area], JSON_THROW_ON_ERROR),
            'active' => 'true',
        ]);

        $userId = (int) ($created->insert_id ?? 0);

        if ($userId > 0) {
            $verified = \markUserEmailVerified($userId, date('Y-m-d H:i:s'));
            if (!($verified->success ?? false)) {
                return 0;
            }
        }

        return $userId;
    }

    public function hasLocalPassword(int $userId): bool
    {
        return trim((string) (($this->findUserById($userId)['password'] ?? ''))) !== '';
    }

    public function setLocalPassword(int $userId, string $passwordHash): void
    {
        User::update(['password' => $passwordHash], $userId);
    }

    public function canAccessArea(int $userId, string $area, array $requiredAuthorities = []): bool
    {
        $user = $this->findUserById($userId);
        if ($user === null || $user['deleted'] || !$user['active'] || !in_array($area, $user['area'], true)) {
            return false;
        }

        return $requiredAuthorities === [] || array_intersect($requiredAuthorities, $user['authority']) !== [];
    }

    private function fromUser(object $user): ?array
    {
        if (!($user->exists ?? false)) {
            return null;
        }

        return [
            'id' => (int) ($user->id ?? 0),
            'email' => (string) ($user->email ?? ''),
            'password' => (string) ($user->password ?? ''),
            'phone' => (string) ($user->phone ?? ''),
            'active' => (bool) ($user->active ?? false),
            'deleted' => (bool) ($user->deleted ?? false),
            'area' => is_array($user->area ?? null) ? $user->area : [],
            'authority' => is_array($user->authority ?? null) ? $user->authority : [],
        ];
    }
}
