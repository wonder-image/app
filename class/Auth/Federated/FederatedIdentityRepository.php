<?php

namespace Wonder\Auth\Federated;

final class FederatedIdentityRepository
{
    private string $table;

    public function __construct(string $table = 'auth_federated')
    {
        $this->table = $table;
    }

    public function findByProviderIdentity(string $provider, string $providerUserId): ?array
    {
        $provider = FederatedProvider::normalize($provider);
        $providerUserId = trim($providerUserId);

        if ($provider === '' || $providerUserId === '') {
            return null;
        }

        $sql = \sqlSelect($this->table, [
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'deleted' => 'false',
        ], 1);

        return $sql->exists ? (array) $sql->row : null;
    }

    public function findByUserProvider(int $userId, string $provider): ?array
    {
        $provider = FederatedProvider::normalize($provider);

        if ($userId <= 0 || $provider === '') {
            return null;
        }

        $sql = \sqlSelect($this->table, [
            'user_id' => $userId,
            'provider' => $provider,
            'deleted' => 'false',
        ], 1);

        return $sql->exists ? (array) $sql->row : null;
    }

    public function findByUserId(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $sql = \sqlSelect($this->table, [
            'user_id' => $userId,
            'deleted' => 'false',
        ]);

        $rows = [];
        if (isset($sql->row[0]) && is_array($sql->row[0])) {
            foreach ($sql->row as $row) {
                $rows[] = (array) $row;
            }

            return $rows;
        }

        if ($sql->exists && is_array($sql->row)) {
            $rows[] = (array) $sql->row;
        }

        return $rows;
    }

    public function linkIdentity(int $userId, FederatedIdentityPayload $identity): int
    {
        if ($userId <= 0 || !$identity->isValid()) {
            throw new \InvalidArgumentException('invalid_federated_link_payload');
        }

        $existing = $this->findByProviderIdentity($identity->provider, $identity->providerUserId);
        if (is_array($existing)) {
            $existingUserId = (int) ($existing['user_id'] ?? 0);
            if ($existingUserId !== $userId) {
                throw new \RuntimeException('federated_identity_already_linked_to_another_user');
            }

            $this->touchLogin((int) ($existing['id'] ?? 0), $identity);

            return (int) ($existing['id'] ?? 0);
        }

        $byProviderForUser = $this->findByUserProvider($userId, $identity->provider);
        if (is_array($byProviderForUser)) {
            $currentProviderUserId = (string) ($byProviderForUser['provider_user_id'] ?? '');
            if ($currentProviderUserId !== $identity->providerUserId) {
                throw new \RuntimeException('federated_provider_already_used_for_different_subject');
            }

            $this->touchLogin((int) ($byProviderForUser['id'] ?? 0), $identity);

            return (int) ($byProviderForUser['id'] ?? 0);
        }

        $metaJson = $this->encodeMeta([
            'claims' => $identity->rawClaims,
        ]);

        $insert = \sqlInsert($this->table, [
            'user_id' => $userId,
            'provider' => $identity->provider,
            'provider_user_id' => $identity->providerUserId,
            'provider_email' => $identity->email,
            'provider_email_verified' => $identity->emailVerified ? 'true' : 'false',
            'last_login_at' => date('Y-m-d H:i:s'),
            'meta' => $metaJson,
        ]);

        return (int) ($insert->insert_id ?? 0);
    }

    public function touchLogin(int $id, FederatedIdentityPayload $identity): void
    {
        if ($id <= 0) {
            return;
        }

        \sqlModify($this->table, [
            'provider_email' => $identity->email,
            'provider_email_verified' => $identity->emailVerified ? 'true' : 'false',
            'last_login_at' => date('Y-m-d H:i:s'),
            'meta' => $this->encodeMeta([
                'claims' => $identity->rawClaims,
            ]),
        ], 'id', $id);
    }

    private function encodeMeta(array $meta): string
    {
        $json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '{}' : $json;
    }
}
