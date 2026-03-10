<?php

    namespace Wonder\Consent\Repository;

    use Wonder\Consent\ConsentException;

    class MarketingOptInTokenRepository extends AbstractConsentRepository
    {
        /**
         * Crea un token double opt-in.
         *
         * @throws ConsentException
         */
        public function create(
            int $userId,
            int $consentEventId,
            string $token,
            string $expiresAt,
            ?string $createdAt = null
        ): int {

            if ($userId <= 0 || $consentEventId <= 0) {
                throw new ConsentException('Parametri non validi per marketing_optin_tokens');
            }

            $token = trim($token);

            if ($token === '') {
                throw new ConsentException('Token marketing vuoto');
            }

            $values = [
                'user_id' => $userId,
                'consent_event_id' => $consentEventId,
                'token' => $token,
                'expires_at' => $expiresAt,
                'confirmed_at' => null,
                'revoked_at' => null,
                'created_at' => $createdAt ?? $this->now(),
            ];

            $insert = $this->query->Insert('marketing_optin_tokens', $values);

            if (!$insert->success || empty($insert->insert_id)) {
                throw new ConsentException('Errore durante la creazione del token marketing');
            }

            return (int) $insert->insert_id;

        }

        /**
         * Recupera token con lock transazionale (FOR UPDATE).
         *
         * @return array<string, mixed>|null
         */
        public function findByTokenForUpdate(string $token): ?array
        {

            $token = trim($token);

            if ($token === '') {
                return null;
            }

            $sql = "SELECT * FROM `marketing_optin_tokens` ";
            $sql .= "WHERE `token` = '".$this->escape($token)."' ";
            $sql .= "LIMIT 1 FOR UPDATE";

            return $this->fetchOne($sql);

        }

        public function markConfirmed(int $tokenId, ?string $confirmedAt = null): void
        {

            if ($tokenId <= 0) {
                return;
            }

            $confirmedAt = $confirmedAt ?? $this->now();

            $sql = "UPDATE `marketing_optin_tokens` SET ";
            $sql .= "`confirmed_at` = ".$this->toSqlValue($confirmedAt)." ";
            $sql .= "WHERE `id` = ".(int) $tokenId;

            $this->mysqli->query($sql);

        }

        public function revokeOpenByUserId(int $userId, ?string $revokedAt = null): void
        {

            if ($userId <= 0) {
                return;
            }

            $revokedAt = $revokedAt ?? $this->now();

            $sql = "UPDATE `marketing_optin_tokens` SET ";
            $sql .= "`revoked_at` = ".$this->toSqlValue($revokedAt)." ";
            $sql .= "WHERE `user_id` = ".(int) $userId." ";
            $sql .= "AND `confirmed_at` IS NULL ";
            $sql .= "AND `revoked_at` IS NULL";

            $this->mysqli->query($sql);

        }
    }

