<?php

namespace Wonder\Auth;

use InvalidArgumentException;
use RuntimeException;
use Wonder\Sql\Connection;

final class OneTimeToken
{
    public function __construct(
        private readonly string $purpose,
        private readonly int $ttlSeconds = 3600,
    ) {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,49}$/', $this->purpose)) {
            throw new InvalidArgumentException('auth_token_purpose_invalid');
        }
    }

    /** @return object{token:string,selector:string,expires_at:string} */
    public function issue(
        int $subjectUserId,
        ?int $actorUserId = null,
        ?string $continueUrl = null,
        array $metadata = [],
        bool $revokeOpenTokens = true,
    ): object {
        if ($subjectUserId <= 0) {
            throw new InvalidArgumentException('auth_token_subject_invalid');
        }

        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + max(60, $this->ttlSeconds));

        if ($revokeOpenTokens) {
            $this->revokeOpenForSubject($subjectUserId, $now);
        }

        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($metadataJson)) {
            throw new InvalidArgumentException('auth_token_metadata_invalid');
        }

        $insert = \sqlInsert('auth_one_time_tokens', [
            'purpose' => $this->purpose,
            'subject_user_id' => $subjectUserId,
            'actor_user_id' => $actorUserId,
            'selector' => $selector,
            'validator_hash' => hash('sha256', $validator),
            'continue_url' => $continueUrl,
            'metadata_json' => $metadataJson,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);

        if (!($insert->success ?? false)) {
            throw new RuntimeException('auth_token_issue_failed');
        }

        return (object) [
            'id' => (int) ($insert->insert_id ?? 0),
            'token' => $selector.'.'.$validator,
            'selector' => $selector,
            'expires_at' => $expiresAt,
        ];
    }

    /** @return object{id:int,subject_user_id:int,actor_user_id:?int,continue_url:?string,metadata:array}|null */
    public function consume(string $token): ?object
    {
        $record = $this->inspect($token);

        if ($record === null || !$this->markConsumed($record->id)) {
            return null;
        }

        return $record;
    }

    /** Validate a bearer token without consuming it (for GET previews only). */
    public function inspect(string $token): ?object
    {
        [$selector, $validator] = $this->split($token);

        if ($selector === '' || $validator === '') {
            return null;
        }

        $query = \sqlSelect('auth_one_time_tokens', [
            'purpose' => $this->purpose,
            'selector' => $selector,
        ], 1);

        if (!($query->exists ?? false) || !is_array($query->row ?? null)) {
            return null;
        }

        $row = $query->row;
        $expected = (string) ($row['validator_hash'] ?? '');

        if (!empty($row['consumed_at'])
            || !empty($row['revoked_at'])
            || empty($row['expires_at'])
            || strtotime((string) $row['expires_at']) < time()
            || $expected === ''
            || !hash_equals($expected, hash('sha256', $validator))) {
            return null;
        }

        $metadata = json_decode((string) ($row['metadata_json'] ?? ''), true);

        return (object) [
            'id' => (int) $row['id'],
            'subject_user_id' => (int) ($row['subject_user_id'] ?? 0),
            'actor_user_id' => !empty($row['actor_user_id']) ? (int) $row['actor_user_id'] : null,
            'continue_url' => isset($row['continue_url']) ? (string) $row['continue_url'] : null,
            'metadata' => is_array($metadata) ? $metadata : [],
        ];
    }

    public function revokeOpenForSubject(int $subjectUserId, ?string $revokedAt = null): void
    {
        if ($subjectUserId <= 0) {
            return;
        }

        $query = \sqlSelect('auth_one_time_tokens', [
            'purpose' => $this->purpose,
            'subject_user_id' => $subjectUserId,
        ]);

        if (!($query->exists ?? false)) {
            return;
        }

        $rows = isset($query->row['id']) ? [$query->row] : (array) $query->row;
        $revokedAt ??= date('Y-m-d H:i:s');

        foreach ($rows as $row) {
            if (!is_array($row) || !empty($row['consumed_at']) || !empty($row['revoked_at'])) {
                continue;
            }

            \sqlModify('auth_one_time_tokens', ['revoked_at' => $revokedAt], 'id', (int) ($row['id'] ?? 0));
        }
    }

    /** @return array{0:string,1:string} */
    private function split(string $token): array
    {
        $parts = explode('.', trim($token), 2);

        if (count($parts) !== 2) {
            return ['', ''];
        }

        $selector = trim($parts[0]);
        $validator = trim($parts[1]);

        if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
            return ['', ''];
        }

        return [$selector, $validator];
    }

    private function markConsumed(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $mysqli = Connection::Connect('main');
        $now = date('Y-m-d H:i:s');
        $statement = $mysqli->prepare(
            'UPDATE `auth_one_time_tokens` SET `consumed_at` = ? '
            .'WHERE `id` = ? AND `consumed_at` IS NULL AND `revoked_at` IS NULL'
        );

        if ($statement === false) {
            return false;
        }

        $statement->bind_param('si', $now, $id);
        $statement->execute();
        $changed = $statement->affected_rows;
        $statement->close();

        return $changed === 1;
    }
}
