<?php

    namespace Wonder\Consent\Repository;

    use Wonder\Consent\ConsentDictionary;
    use Wonder\Consent\ConsentException;

    class ConsentConfirmationTokenRepository extends AbstractConsentRepository
    {
        /**
         * Crea un token di conferma.
         *
         * @throws ConsentException
         */
        public function create(
            int $userId,
            string $token,
            string $expiresAt,
            ?string $createdAt = null,
            string $tokenType = 'user_email_verification',
            ?string $languageCode = null,
            ?string $continueUrl = null,
            mixed $metadata = null
        ): int {

            if ($userId <= 0) {
                throw new ConsentException('Parametri non validi per consent_confirmation_tokens');
            }

            $token = trim($token);

            if ($token === '') {
                throw new ConsentException('Token consenso vuoto');
            }

            $tokenType = ConsentDictionary::normalizeConsentType($tokenType);

            if ($tokenType === '') {
                throw new ConsentException('token_type non valido per consent_confirmation_tokens');
            }

            $languageCode = $languageCode !== null
                ? ConsentDictionary::normalizeLanguageCode($languageCode)
                : null;

            if (is_array($metadata) || is_object($metadata)) {
                $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $metadata = ($encoded !== false) ? $encoded : null;
            }

            $values = [
                'token_type' => $tokenType,
                'user_id' => $userId,
                'token' => $token,
                'language_code' => $languageCode,
                'continue_url' => $continueUrl,
                'metadata_json' => $metadata,
                'expires_at' => $expiresAt,
                'confirmed_at' => null,
                'revoked_at' => null,
                'created_at' => $createdAt ?? $this->now(),
            ];

            $insert = $this->query->Insert('consent_confirmation_tokens', $values);

            if (!$insert->success || empty($insert->insert_id)) {
                throw new ConsentException('Errore durante la creazione del token consenso');
            }

            return (int) $insert->insert_id;

        }

        /**
         * Recupera token con lock transazionale (FOR UPDATE).
         *
         * @return array<string, mixed>|null
         */
        public function findByTokenForUpdate(string $token, ?string $tokenType = null): ?array
        {

            $token = trim($token);

            if ($token === '') {
                return null;
            }

            if ($tokenType !== null) {
                $tokenType = ConsentDictionary::normalizeConsentType($tokenType);
            }

            $sql = "SELECT * FROM `consent_confirmation_tokens` ";
            $sql .= "WHERE `token` = '".$this->escape($token)."' ";

            if ($tokenType !== null && $tokenType !== '') {
                $sql .= "AND `token_type` = '".$this->escape($tokenType)."' ";
            }

            $sql .= "LIMIT 1 FOR UPDATE";

            return $this->fetchOne($sql);

        }

        public function markConfirmed(int $tokenId, ?string $confirmedAt = null): void
        {

            if ($tokenId <= 0) {
                return;
            }

            $confirmedAt = $confirmedAt ?? $this->now();

            $sql = "UPDATE `consent_confirmation_tokens` SET ";
            $sql .= "`confirmed_at` = ".$this->toSqlValue($confirmedAt)." ";
            $sql .= "WHERE `id` = ".(int) $tokenId;

            $this->mysqli->query($sql);

        }

        public function revokeOpenByUserId(int $userId, ?string $revokedAt = null, ?string $tokenType = null): void
        {

            if ($userId <= 0) {
                return;
            }

            $revokedAt = $revokedAt ?? $this->now();

            $sql = "UPDATE `consent_confirmation_tokens` SET ";
            $sql .= "`revoked_at` = ".$this->toSqlValue($revokedAt)." ";
            $sql .= "WHERE `user_id` = ".(int) $userId." ";

            if ($tokenType !== null) {
                $tokenType = ConsentDictionary::normalizeConsentType($tokenType);
                if ($tokenType !== '') {
                    $sql .= "AND `token_type` = '".$this->escape($tokenType)."' ";
                }
            }

            $sql .= "AND `confirmed_at` IS NULL ";
            $sql .= "AND `revoked_at` IS NULL";

            $this->mysqli->query($sql);

        }
    }
