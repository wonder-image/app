<?php

namespace Wonder\OAuth;

use Closure;
use Wonder\OAuth\Store\OAuthStore;

/** Grant e token opachi: emissione, rotazione del refresh con rilevamento del riuso, revoca. */
final class TokenIssuer
{
    private readonly Closure $clock;

    public function __construct(private readonly OAuthStore $store, ?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /** @param list<string> $scopes quelli approvati nel consenso */
    public function grantFor(int $userId, string $clientId, string $resource, array $scopes = []): int
    {
        $now = $this->date();
        $scope = Scope::join($scopes);
        $grant = $this->store->findActiveGrant($userId, $clientId, $resource);
        if ($grant !== null) {
            if ((string) $grant['scope'] === $scope && $this->usableGrant((int) $grant['id']) !== null) {
                // Il consenso è stato richiesto di nuovo: la scadenza assoluta riparte da qui.
                $this->store->updateGrant((int) $grant['id'], ['consented_at' => $now]);
                return (int) $grant['id'];
            }
            // Grant non più utilizzabile, oppure con altri scope: i token già emessi
            // non devono cambiare potere, quindi si chiude e se ne apre uno nuovo.
            $this->store->revokeGrant((int) $grant['id'], $now);
        }
        return $this->store->createGrant(['user_id' => $userId, 'client_id' => $clientId, 'resource' => $resource, 'scope' => $scope, 'consented_at' => $now]);
    }

    public function issue(int $grantId): array
    {
        $accessTtl = Config::accessTokenTtl();
        $scope = (string) ($this->store->findGrant($grantId)['scope'] ?? '');
        return [
            'access_token' => $this->mint('access', 'wio_at_', $grantId, $accessTtl),
            'token_type' => 'Bearer',
            'expires_in' => $accessTtl,
            'refresh_token' => $this->mint('refresh', 'wio_rt_', $grantId, Config::refreshTokenTtl()),
        ] + ($scope !== '' ? ['scope' => $scope] : []);
    }

    /** `$scope` può solo restringere a parole: il token nuovo ha comunque gli scope del grant. */
    public function refresh(string $refreshToken, string $clientId, ?string $resource, ?string $scope = null): array
    {
        $row = $this->lookup('refresh', $refreshToken, 'wio_rt_');
        if ($row === null) {
            throw new OAuthException('invalid_grant', 'Invalid refresh token.');
        }

        $grant = $this->store->findGrant((int) $row['grant_id']);
        if ($grant === null || !hash_equals((string) $grant['client_id'], $clientId)) {
            throw new OAuthException('invalid_grant', 'Invalid refresh token.');
        }

        // Riuso: un refresh già consumato revoca l'intero grant.
        if (!empty($row['consumed_at'])) {
            $this->store->revokeGrant((int) $grant['id'], $this->date());
            throw new OAuthException('invalid_grant', 'Refresh token reuse detected; grant revoked.');
        }

        if (!empty($row['revoked_at']) || strtotime((string) $row['expires_at']) < ($this->clock)()) {
            throw new OAuthException('invalid_grant', 'Refresh token expired or revoked.');
        }

        if ($resource !== null && $resource !== '' && rtrim($resource, '/') !== $grant['resource']) {
            throw new OAuthException('invalid_target', 'Resource does not match the grant.');
        }

        $this->assertUsable((int) $grant['id']);

        $granted = Scope::granted((string) $grant['scope'], ResourceRegistry::findByUrl((string) $grant['resource']) ?? []);
        if (array_diff(Scope::parse((string) $scope), $granted) !== []) {
            throw new OAuthException('invalid_scope', 'Scope exceeds the grant.');
        }

        if (!$this->store->consumeRefresh((int) $row['id'], $this->date())) {
            // Corsa con un'altra richiesta sullo stesso refresh: trattata come riuso.
            $this->store->revokeGrant((int) $grant['id'], $this->date());
            throw new OAuthException('invalid_grant', 'Refresh token reuse detected; grant revoked.');
        }

        return $this->issue((int) $grant['id']);
    }

    public function revokeByToken(string $token, string $clientId): void
    {
        foreach (['access' => 'wio_at_', 'refresh' => 'wio_rt_'] as $kind => $prefix) {
            if (!str_starts_with($token, $prefix)) {
                continue;
            }
            $row = $this->lookup($kind, $token, $prefix);
            $grant = $row !== null ? $this->store->findGrant((int) $row['grant_id']) : null;
            if ($grant !== null && hash_equals((string) $grant['client_id'], $clientId)) {
                $this->store->revokeGrant((int) $grant['id'], $this->date());
            }
        }
    }

    /** Grant ancora utilizzabile: non revocato, entro la scadenza assoluta, client e utente attivi. */
    public function usableGrant(int $grantId): ?array
    {
        $grant = $this->store->findGrant($grantId);
        if ($grant === null || !empty($grant['revoked_at'])
            || strtotime((string) $grant['consented_at']) + Config::grantMaxAge() < ($this->clock)()) {
            return null;
        }
        $client = $this->store->findClient((string) $grant['client_id']);
        if ($client === null || !empty($client['revoked_at'])) {
            return null;
        }
        $user = $this->store->findUser((int) $grant['user_id']);
        if ($user === null || ($user->deleted ?? false) || ($user->active ?? false) !== true) {
            return null;
        }
        return ['grant' => $grant, 'client' => $client, 'user' => $user];
    }

    /** @return array{0:string,1:string}|null */
    public static function parse(string $token, string $prefix): ?array
    {
        if (!str_starts_with($token, $prefix)) {
            return null;
        }
        $parts = explode('.', substr($token, strlen($prefix)), 2);
        if (count($parts) !== 2 || preg_match('/^[a-f0-9]{16}$/', $parts[0]) !== 1 || preg_match('/^[a-f0-9]{64}$/', $parts[1]) !== 1) {
            return null;
        }
        return [$parts[0], $parts[1]];
    }

    /** Riga del token se selector e validator corrispondono (non controlla scadenza e revoca). */
    public function lookup(string $kind, string $token, string $prefix): ?array
    {
        $parsed = self::parse($token, $prefix);
        if ($parsed === null) {
            return null;
        }
        $row = $this->store->findToken($kind, $parsed[0]);
        return ($row !== null && hash_equals((string) $row['validator_hash'], hash('sha256', $parsed[1]))) ? $row : null;
    }

    private function assertUsable(int $grantId): void
    {
        $grant = $this->store->findGrant($grantId);
        $client = $grant !== null ? $this->store->findClient((string) $grant['client_id']) : null;
        if ($client === null || !empty($client['revoked_at'])) {
            throw new OAuthException('invalid_client', 'Client not found.', 401);
        }
        if ($this->usableGrant($grantId) === null) {
            throw new OAuthException('invalid_grant', 'Grant revoked or expired, or user not active.');
        }
    }

    private function mint(string $kind, string $prefix, int $grantId, int $ttl): string
    {
        $selector = bin2hex(random_bytes(8));
        $validator = bin2hex(random_bytes(32));
        $this->store->insertToken($kind, [
            'grant_id' => $grantId,
            'selector' => $selector,
            'validator_hash' => hash('sha256', $validator),
            'expires_at' => date('Y-m-d H:i:s', ($this->clock)() + $ttl),
        ]);
        return $prefix.$selector.'.'.$validator;
    }

    private function date(): string
    {
        return date('Y-m-d H:i:s', ($this->clock)());
    }
}
