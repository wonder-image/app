<?php

namespace Wonder\OAuth\Store;

interface OAuthStore
{
    // client
    public function createClient(array $row): void;                 // row: client_id, client_name, redirect_uris (json string), token_endpoint_auth_method, registration_ip, creation
    public function findClient(string $clientId): ?array;           // null se inesistente o deleted='true' (revoked_at incluso nella riga)
    public function countClientsFromIp(string $ip, string $since): int;
    public function touchClient(string $clientId, string $now): void;
    public function deleteClient(string $clientId): void;
    // grant
    public function createGrant(array $row): int;                   // row: user_id, client_id, resource, scope, consented_at
    public function findGrant(int $id): ?array;
    public function findActiveGrant(int $userId, string $clientId, string $resource): ?array;
    public function updateGrant(int $id, array $values): void;
    public function revokeGrant(int $id, string $now): void;        // revoca anche tutti gli access e refresh del grant
    /** @return list<array> grant non revocati, più recenti prima; $userId null = tutti */
    public function listActiveGrants(?int $userId): array;
    // token ($kind = 'access' | 'refresh')
    public function insertToken(string $kind, array $row): void;    // row: grant_id, selector, validator_hash, expires_at
    public function findToken(string $kind, string $selector): ?array;
    public function consumeRefresh(int $id, string $now): bool;     // atomico: true solo se consumed_at e revoked_at erano NULL
    // codici
    public function issueCode(int $userId, array $metadata, int $ttl): string;
    /** @return array{user_id:int, metadata:array}|null */
    public function consumeCode(string $code): ?array;
    // utenti
    public function findUser(int $id): ?object;                     // oggetto di infoUser(); null se inesistente
    // pulizia
    public function purgeTokens(string $before): int;
    public function purgeIdleClients(string $idleBefore): int;
    public function revokeGrantsConsentedBefore(string $before, string $now): int;
}
