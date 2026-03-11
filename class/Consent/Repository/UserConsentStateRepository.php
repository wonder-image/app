<?php

    namespace Wonder\Consent\Repository;

    use Wonder\Consent\ConsentDictionary;
    use Wonder\Consent\ConsentException;

    class UserConsentStateRepository extends AbstractConsentRepository
    {
        /**
         * Upsert stato corrente consenso per (user_id, consent_type).
         *
         * @throws ConsentException
         */
        public function upsert(
            int $userId,
            string $consentType,
            string $currentStatus,
            ?int $legalDocumentId,
            int $lastEventId,
            ?string $updatedAt = null
        ): void {

            if ($userId <= 0) {
                throw new ConsentException('user_id non valido per user_consent_state');
            }

            if ($lastEventId <= 0) {
                throw new ConsentException('last_event_id non valido per user_consent_state');
            }

            $consentType = ConsentDictionary::normalizeConsentType($consentType);

            if ($consentType === '') {
                throw new ConsentException('consent_type non valido per user_consent_state');
            }

            ConsentDictionary::assertAllowed($currentStatus, ConsentDictionary::statuses(), 'current_status');

            $updatedAt = $updatedAt ?? $this->now();

            $sql = "INSERT INTO `user_consent_state` (`user_id`, `consent_type`, `current_status`, `legal_document_id`, `last_event_id`, `updated_at`) VALUES (";
            $sql .= (int) $userId.", ";
            $sql .= $this->toSqlValue($consentType).", ";
            $sql .= $this->toSqlValue($currentStatus).", ";
            $sql .= $this->toSqlValue($legalDocumentId).", ";
            $sql .= (int) $lastEventId.", ";
            $sql .= $this->toSqlValue($updatedAt);
            $sql .= ") ON DUPLICATE KEY UPDATE ";
            $sql .= "`current_status` = VALUES(`current_status`), ";
            $sql .= "`legal_document_id` = VALUES(`legal_document_id`), ";
            $sql .= "`last_event_id` = VALUES(`last_event_id`), ";
            $sql .= "`updated_at` = VALUES(`updated_at`)";

            if ($this->mysqli->query($sql) === false) {
                throw new ConsentException('Errore durante upsert di user_consent_state');
            }

        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function getByUserId(int $userId): array
        {

            if ($userId <= 0) {
                return [];
            }

            $sql = "SELECT ";
            $sql .= "ucs.user_id, ucs.consent_type, ucs.current_status, ucs.legal_document_id, ucs.last_event_id, ucs.updated_at, ";
            $sql .= "ce.occurred_at AS last_event_occurred_at, ce.action AS last_event_action, ";
            $sql .= "ld.doc_type AS document_type, ld.version AS document_version, ld.language_code AS document_language_code, ld.content_hash AS document_content_hash ";
            $sql .= "FROM `user_consent_state` ucs ";
            $sql .= "LEFT JOIN `consent_events` ce ON ce.id = ucs.last_event_id ";
            $sql .= "LEFT JOIN `legal_documents` ld ON ld.id = ucs.legal_document_id ";
            $sql .= "WHERE ucs.user_id = ".(int) $userId." ";
            $sql .= "ORDER BY ucs.consent_type ASC";

            return $this->fetchAll($sql);

        }
    }
