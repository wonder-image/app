<?php

namespace Wonder\OAuth\Store;

use mysqli;
use RuntimeException;
use Wonder\Auth\OneTimeToken;
use Wonder\OAuth\Config;
use Wonder\Sql\Connection;

/** Persistenza mysqli. Solo prepared statement: nessun valore concatenato nell'SQL. */
final class SqlStore implements OAuthStore
{
    private const TOKEN_TABLE = ['access' => 'oauth_access_tokens', 'refresh' => 'oauth_refresh_tokens'];

    private function db(): mysqli { return Connection::Connect('main'); }

    /** @return list<array> */
    private function rows(string $sql, string $types = '', array $params = []): array
    {
        $stmt = $this->db()->prepare($sql);
        if ($stmt === false) { throw new RuntimeException('oauth_sql_prepare_failed'); }
        if ($types !== '') { $stmt->bind_param($types, ...$params); }
        if (!$stmt->execute()) {
            error_log('oauth_sql: '.$stmt->error);
            $stmt->close();
            throw new RuntimeException('oauth_sql_execute_failed');
        }
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    }

    private function exec(string $sql, string $types = '', array $params = []): int
    {
        $stmt = $this->db()->prepare($sql);
        if ($stmt === false) { throw new RuntimeException('oauth_sql_prepare_failed'); }
        if ($types !== '') { $stmt->bind_param($types, ...$params); }
        if (!$stmt->execute()) {
            error_log('oauth_sql: '.$stmt->error);
            $stmt->close();
            throw new RuntimeException('oauth_sql_execute_failed');
        }
        $affected = $stmt->affected_rows;
        $insertId = $stmt->insert_id;
        $stmt->close();
        return str_starts_with(ltrim($sql), 'INSERT') ? (int) $insertId : (int) $affected;
    }

    public function createClient(array $row): void
    {
        $this->exec('INSERT INTO `oauth_clients` (`client_id`,`client_name`,`redirect_uris`,`token_endpoint_auth_method`,`registration_ip`,`creation`) VALUES (?,?,?,?,?,?)',
            'ssssss', [$row['client_id'], $row['client_name'], $row['redirect_uris'], $row['token_endpoint_auth_method'], $row['registration_ip'], $row['creation']]);
    }

    public function findClient(string $clientId): ?array
    {
        return $this->rows("SELECT * FROM `oauth_clients` WHERE `client_id` = ? AND `deleted` = 'false' LIMIT 1", 's', [$clientId])[0] ?? null;
    }

    public function countClientsFromIp(string $ip, string $since): int
    {
        return (int) ($this->rows('SELECT COUNT(*) AS n FROM `oauth_clients` WHERE `registration_ip` = ? AND `creation` >= ?', 'ss', [$ip, $since])[0]['n'] ?? 0);
    }

    public function touchClient(string $clientId, string $now): void
    {
        $this->exec('UPDATE `oauth_clients` SET `last_used_at` = ? WHERE `client_id` = ?', 'ss', [$now, $clientId]);
    }

    public function deleteClient(string $clientId): void
    {
        $this->exec('DELETE FROM `oauth_clients` WHERE `client_id` = ?', 's', [$clientId]);
    }

    public function createGrant(array $row): int
    {
        return $this->exec('INSERT INTO `oauth_grants` (`user_id`,`client_id`,`resource`,`scope`,`consented_at`) VALUES (?,?,?,?,?)',
            'issss', [(int) $row['user_id'], $row['client_id'], $row['resource'], $row['scope'] ?? '', $row['consented_at']]);
    }

    public function findGrant(int $id): ?array
    {
        return $this->rows('SELECT * FROM `oauth_grants` WHERE `id` = ? LIMIT 1', 'i', [$id])[0] ?? null;
    }

    public function findActiveGrant(int $userId, string $clientId, string $resource): ?array
    {
        return $this->rows('SELECT * FROM `oauth_grants` WHERE `user_id` = ? AND `client_id` = ? AND `resource` = ? AND `revoked_at` IS NULL ORDER BY `id` DESC LIMIT 1',
            'iss', [$userId, $clientId, $resource])[0] ?? null;
    }

    public function updateGrant(int $id, array $values): void
    {
        $allowed = array_intersect_key($values, array_flip(['consented_at', 'last_used_at']));
        foreach ($allowed as $column => $value) {
            $this->exec("UPDATE `oauth_grants` SET `{$column}` = ? WHERE `id` = ?", 'si', [$value, $id]);
        }
    }

    public function revokeGrant(int $id, string $now): void
    {
        $this->exec('UPDATE `oauth_grants` SET `revoked_at` = ? WHERE `id` = ? AND `revoked_at` IS NULL', 'si', [$now, $id]);
        $this->exec('UPDATE `oauth_access_tokens` SET `revoked_at` = ? WHERE `grant_id` = ? AND `revoked_at` IS NULL', 'si', [$now, $id]);
        $this->exec('UPDATE `oauth_refresh_tokens` SET `revoked_at` = ? WHERE `grant_id` = ? AND `revoked_at` IS NULL', 'si', [$now, $id]);
    }

    public function listActiveGrants(?int $userId): array
    {
        return $userId === null
            ? $this->rows('SELECT * FROM `oauth_grants` WHERE `revoked_at` IS NULL ORDER BY `consented_at` DESC')
            : $this->rows('SELECT * FROM `oauth_grants` WHERE `revoked_at` IS NULL AND `user_id` = ? ORDER BY `consented_at` DESC', 'i', [$userId]);
    }

    public function insertToken(string $kind, array $row): void
    {
        $table = self::TOKEN_TABLE[$kind];
        $this->exec("INSERT INTO `{$table}` (`grant_id`,`selector`,`validator_hash`,`expires_at`) VALUES (?,?,?,?)",
            'isss', [(int) $row['grant_id'], $row['selector'], $row['validator_hash'], $row['expires_at']]);
    }

    public function findToken(string $kind, string $selector): ?array
    {
        $table = self::TOKEN_TABLE[$kind];
        return $this->rows("SELECT * FROM `{$table}` WHERE `selector` = ? LIMIT 1", 's', [$selector])[0] ?? null;
    }

    public function consumeRefresh(int $id, string $now): bool
    {
        return $this->exec('UPDATE `oauth_refresh_tokens` SET `consumed_at` = ? WHERE `id` = ? AND `consumed_at` IS NULL AND `revoked_at` IS NULL', 'si', [$now, $id]) === 1;
    }

    public function issueCode(int $userId, array $metadata, int $ttl): string
    {
        // revokeOpenTokens: false → due flussi paralleli dello stesso utente non si annullano a vicenda.
        return (new OneTimeToken('oauth_code', $ttl))->issue($userId, null, null, $metadata, false)->token;
    }

    public function consumeCode(string $code): ?array
    {
        $record = (new OneTimeToken('oauth_code', Config::CODE_TTL))->consume($code);
        return $record === null ? null : ['user_id' => $record->subject_user_id, 'metadata' => $record->metadata];
    }

    public function findUser(int $id): ?object
    {
        $user = \infoUser($id);
        return ($user->exists ?? false) ? $user : null;
    }

    public function purgeTokens(string $before): int
    {
        $n = $this->exec('DELETE FROM `oauth_access_tokens` WHERE `expires_at` < ? OR `revoked_at` < ?', 'ss', [$before, $before]);
        return $n + $this->exec('DELETE FROM `oauth_refresh_tokens` WHERE `expires_at` < ? OR `consumed_at` < ? OR `revoked_at` < ?', 'sss', [$before, $before, $before]);
    }

    public function purgeIdleClients(string $idleBefore): int
    {
        return $this->exec('DELETE c FROM `oauth_clients` c WHERE COALESCE(c.`last_used_at`, c.`creation`) < ? '
            .'AND NOT EXISTS (SELECT 1 FROM `oauth_grants` g WHERE g.`client_id` = c.`client_id` AND g.`revoked_at` IS NULL)', 's', [$idleBefore]);
    }

    public function revokeGrantsConsentedBefore(string $before, string $now): int
    {
        $ids = array_column($this->rows('SELECT `id` FROM `oauth_grants` WHERE `revoked_at` IS NULL AND `consented_at` < ?', 's', [$before]), 'id');
        foreach ($ids as $id) { $this->revokeGrant((int) $id, $now); }
        return count($ids);
    }
}
