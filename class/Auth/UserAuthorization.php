<?php

namespace Wonder\Auth;

final class UserAuthorization
{
    private const ALLOWED = 'allowed';
    private const LOGIN = 'login';
    private const FORBIDDEN = 'forbidden';

    private function __construct(
        private readonly string $outcome,
        private readonly ?int $alert = null,
    ) {}

    public static function evaluate(?object $user, string $area, array $requiredAuthorities = [], bool $expiredPost = false): self
    {
        if ($user === null) {
            return new self(self::LOGIN, $expiredPost ? 917 : null);
        }

        if (empty($user->exists)) {
            return new self(self::LOGIN, 901);
        }

        if (!empty($user->deleted)) {
            return new self(self::LOGIN, 912);
        }

        if (empty($user->active)) {
            return new self(self::LOGIN, 909);
        }

        $areas = isset($user->area) && is_array($user->area) ? $user->area : [];
        if (!in_array($area, $areas, true)) {
            return new self(self::LOGIN, 911);
        }

        $authorities = isset($user->authority) && is_array($user->authority) ? $user->authority : [];
        if ($requiredAuthorities !== [] && array_intersect($requiredAuthorities, $authorities) === []) {
            return new self(self::FORBIDDEN);
        }

        return new self(self::ALLOWED);
    }

    public function isAllowed(): bool
    {
        return $this->outcome === self::ALLOWED;
    }

    public function requiresLogin(): bool
    {
        return $this->outcome === self::LOGIN;
    }

    public function isForbidden(): bool
    {
        return $this->outcome === self::FORBIDDEN;
    }

    public function alert(): ?int
    {
        return $this->alert;
    }
}
