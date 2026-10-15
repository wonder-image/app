<?php

namespace Wonder\OAuth\Store;

/** Persistenza in memoria per i test: stesso contratto di SqlStore. */
final class MemoryStore implements OAuthStore
{
    public array $tables = ['clients' => [], 'grants' => [], 'access' => [], 'refresh' => [], 'codes' => []];
    public array $users = [];
    private int $seq = 0;

    public function now(): string { return date('Y-m-d H:i:s'); }

    public function createClient(array $row): void
    {
        $this->tables['clients'][$row['client_id']] = $row + ['id' => ++$this->seq, 'last_used_at' => null, 'revoked_at' => null, 'deleted' => 'false', 'client_secret_hash' => null];
    }

    public function findClient(string $clientId): ?array
    {
        $row = $this->tables['clients'][$clientId] ?? null;
        return ($row === null || $row['deleted'] === 'true') ? null : $row;
    }

    public function countClientsFromIp(string $ip, string $since): int
    {
        return count(array_filter($this->tables['clients'], static fn ($c) => $c['registration_ip'] === $ip && $c['creation'] >= $since));
    }

    public function touchClient(string $clientId, string $now): void
    {
        if (isset($this->tables['clients'][$clientId])) {
            $this->tables['clients'][$clientId]['last_used_at'] = $now;
        }
    }

    public function deleteClient(string $clientId): void { unset($this->tables['clients'][$clientId]); }

    public function createGrant(array $row): int
    {
        $id = ++$this->seq;
        $this->tables['grants'][$id] = $row + ['id' => $id, 'last_used_at' => null, 'revoked_at' => null];
        return $id;
    }

    public function findGrant(int $id): ?array { return $this->tables['grants'][$id] ?? null; }

    public function findActiveGrant(int $userId, string $clientId, string $resource): ?array
    {
        foreach ($this->tables['grants'] as $g) {
            if ((int) $g['user_id'] === $userId && $g['client_id'] === $clientId && $g['resource'] === $resource && $g['revoked_at'] === null) {
                return $g;
            }
        }
        return null;
    }

    public function updateGrant(int $id, array $values): void
    {
        if (isset($this->tables['grants'][$id])) {
            $this->tables['grants'][$id] = $values + $this->tables['grants'][$id];
        }
    }

    public function revokeGrant(int $id, string $now): void
    {
        if (isset($this->tables['grants'][$id]) && $this->tables['grants'][$id]['revoked_at'] === null) {
            $this->tables['grants'][$id]['revoked_at'] = $now;
        }
        foreach (['access', 'refresh'] as $kind) {
            foreach ($this->tables[$kind] as $sel => $t) {
                if ((int) $t['grant_id'] === $id && $t['revoked_at'] === null) {
                    $this->tables[$kind][$sel]['revoked_at'] = $now;
                }
            }
        }
    }

    public function listActiveGrants(?int $userId): array
    {
        $rows = array_values(array_filter($this->tables['grants'], static fn ($g) => $g['revoked_at'] === null && ($userId === null || (int) $g['user_id'] === $userId)));
        usort($rows, static fn ($a, $b) => strcmp($b['consented_at'], $a['consented_at']));
        return $rows;
    }

    public function insertToken(string $kind, array $row): void
    {
        $this->tables[$kind][$row['selector']] = $row + ['id' => ++$this->seq, 'revoked_at' => null, 'consumed_at' => null];
    }

    public function findToken(string $kind, string $selector): ?array { return $this->tables[$kind][$selector] ?? null; }

    public function consumeRefresh(int $id, string $now): bool
    {
        foreach ($this->tables['refresh'] as $sel => $t) {
            if ($t['id'] === $id) {
                if ($t['consumed_at'] !== null || $t['revoked_at'] !== null) {
                    return false;
                }
                $this->tables['refresh'][$sel]['consumed_at'] = $now;
                return true;
            }
        }
        return false;
    }

    public function issueCode(int $userId, array $metadata, int $ttl): string
    {
        $code = bin2hex(random_bytes(12)).'.'.bin2hex(random_bytes(32));
        $this->tables['codes'][$code] = ['user_id' => $userId, 'metadata' => $metadata, 'expires' => time() + $ttl, 'used' => false];
        return $code;
    }

    public function consumeCode(string $code): ?array
    {
        $row = $this->tables['codes'][$code] ?? null;
        if ($row === null || $row['used'] || $row['expires'] < time()) {
            return null;
        }
        $this->tables['codes'][$code]['used'] = true;
        return ['user_id' => $row['user_id'], 'metadata' => $row['metadata']];
    }

    public function findUser(int $id): ?object { return $this->users[$id] ?? null; }

    public function purgeTokens(string $before): int
    {
        $n = 0;
        foreach (['access', 'refresh'] as $kind) {
            foreach ($this->tables[$kind] as $sel => $t) {
                $dead = $t['expires_at'] < $before
                    || (($t['consumed_at'] ?? null) !== null && $t['consumed_at'] < $before)
                    || (($t['revoked_at'] ?? null) !== null && $t['revoked_at'] < $before);
                if ($dead) { unset($this->tables[$kind][$sel]); $n++; }
            }
        }
        return $n;
    }

    public function purgeIdleClients(string $idleBefore): int
    {
        $n = 0;
        foreach ($this->tables['clients'] as $id => $c) {
            $hasActive = false;
            foreach ($this->tables['grants'] as $g) {
                if ($g['client_id'] === $id && $g['revoked_at'] === null) { $hasActive = true; break; }
            }
            $last = $c['last_used_at'] ?? $c['creation'];
            if (!$hasActive && $last < $idleBefore) { unset($this->tables['clients'][$id]); $n++; }
        }
        return $n;
    }

    public function revokeGrantsConsentedBefore(string $before, string $now): int
    {
        $n = 0;
        foreach ($this->tables['grants'] as $id => $g) {
            if ($g['revoked_at'] === null && $g['consented_at'] < $before) { $this->revokeGrant($id, $now); $n++; }
        }
        return $n;
    }
}
