<?php

namespace Wonder\Auth\Federated\Bridge;

use Wonder\Auth\Federated\Contract\UserAccountGatewayInterface;
use Wonder\Auth\Federated\FederatedIdentityPayload;

final class LegacyUserAccountGateway implements UserAccountGatewayInterface
{
    private ?string $defaultAuthority;

    public function __construct(?string $defaultAuthority = null)
    {
        $this->defaultAuthority = $defaultAuthority;
    }

    public function findUserByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $user = \infoUser($email, 'email');
        if (!($user->exists ?? false)) {
            return null;
        }

        return $this->mapUserObject($user);
    }

    public function findUserById(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $user = \infoUser($userId, 'id');
        if (!($user->exists ?? false)) {
            return null;
        }

        return $this->mapUserObject($user);
    }

    public function createUserFromFederatedIdentity(FederatedIdentityPayload $identity, string $area): int
    {
        $post = [
            'name' => $identity->name,
            'surname' => $identity->surname,
            'email' => $identity->email,
            'area' => $area,
            'active' => 'true',
        ];

        if ($this->defaultAuthority !== null && $this->defaultAuthority !== '') {
            $post['authority'] = $this->defaultAuthority;
        }

        $result = \user($post, null);

        return (int) ($result->user->id ?? 0);
    }

    public function hasLocalPassword(int $userId): bool
    {
        $user = $this->findUserById($userId);
        if (!is_array($user)) {
            return false;
        }

        return trim((string) ($user['password'] ?? '')) !== '';
    }

    public function setLocalPassword(int $userId, string $passwordHash): void
    {
        if ($userId <= 0) {
            return;
        }

        \sqlModify('user', [
            'password' => $passwordHash,
        ], 'id', $userId);
    }

    public function canAccessArea(int $userId, string $area, array $requiredAuthorities = []): bool
    {
        $user = $this->findUserById($userId);
        if (!is_array($user)) {
            return false;
        }

        if (($user['deleted'] ?? true) || !($user['active'] ?? false)) {
            return false;
        }

        $areas = is_array($user['area'] ?? null) ? $user['area'] : [];
        if (!in_array($area, $areas, true)) {
            return false;
        }

        if (count($requiredAuthorities) === 0) {
            return true;
        }

        $authorities = is_array($user['authority'] ?? null) ? $user['authority'] : [];

        return count(array_intersect($requiredAuthorities, $authorities)) > 0;
    }

    private function mapUserObject(object $user): array
    {
        return [
            'id' => (int) ($user->id ?? 0),
            'email' => (string) ($user->email ?? ''),
            'password' => (string) ($user->password ?? ''),
            'active' => (bool) ($user->active ?? false),
            'deleted' => (bool) ($user->deleted ?? true),
            'area' => is_array($user->area ?? null) ? $user->area : [],
            'authority' => is_array($user->authority ?? null) ? $user->authority : [],
            'raw' => $user,
        ];
    }
}
