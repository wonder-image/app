<?php

    namespace Wonder\Consent\Repository;

    use Wonder\Consent\ConsentDictionary;
    use Wonder\Consent\ConsentException;

    class ConsentEventRepository extends AbstractConsentRepository
    {
        /**
         * Inserisce un evento consenso e ritorna l'id creato.
         *
         * @param array<string, mixed> $payload
         * @throws ConsentException
         */
        public function create(array $payload): int
        {

            $userId = (int) ($payload['user_id'] ?? 0);
            $subjectEmail = trim((string) ($payload['subject_email'] ?? ''));
            $consentType = ConsentDictionary::normalizeConsentType((string) ($payload['consent_type'] ?? ''));
            $action = (string) ($payload['action'] ?? '');

            // Almeno uno dei due identificatori deve essere valorizzato:
            // `user_id` per il signup classico, `subject_email` per i lead
            // raccolti da form pubblici (contatto, newsletter, ecc.).
            if ($userId <= 0 && $subjectEmail === '') {
                throw new ConsentException('Identificatore consenso mancante: serve user_id oppure subject_email');
            }

            if ($consentType === '') {
                throw new ConsentException('consent_type non valido');
            }
            ConsentDictionary::assertAllowed($action, ConsentDictionary::actions(), 'action');

            $source = (string) ($payload['source'] ?? ConsentDictionary::SOURCE_WEB);
            ConsentDictionary::assertAllowed($source, ConsentDictionary::sources(), 'source');

            $occurredAt = (string) ($payload['occurred_at'] ?? $this->now());
            $locale = ConsentDictionary::normalizeLanguageCode((string) ($payload['locale'] ?? 'it'));

            $evidence = $payload['evidence_json'] ?? null;
            if (is_array($evidence) || is_object($evidence)) {
                $encoded = json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $evidence = ($encoded !== false) ? $encoded : null;
            }

            $subjectRefType = trim((string) ($payload['subject_ref_type'] ?? ''));
            $subjectRefId = (int) ($payload['subject_ref_id'] ?? 0);

            $values = [
                'user_id' => $userId > 0 ? $userId : null,
                'subject_email' => $subjectEmail !== '' ? $subjectEmail : null,
                'subject_ref_type' => $subjectRefType !== '' ? $subjectRefType : null,
                'subject_ref_id' => $subjectRefId > 0 ? $subjectRefId : null,
                'consent_type' => $consentType,
                'action' => $action,
                'legal_document_id' => isset($payload['legal_document_id']) ? (int) $payload['legal_document_id'] : null,
                'occurred_at' => $occurredAt,
                'ip_address' => (string) ($payload['ip_address'] ?? ''),
                'user_agent' => (string) ($payload['user_agent'] ?? ''),
                'locale' => $locale,
                'source' => $source,
                'ui_surface' => (string) ($payload['ui_surface'] ?? ''),
                'evidence_json' => $evidence,
                'creation' => (string) ($payload['creation'] ?? $payload['created_at'] ?? $this->now()),
            ];

            $insert = $this->query->Insert('consent_events', $values);

            if (!$insert->success || empty($insert->insert_id)) {
                throw new ConsentException('Errore durante il salvataggio di consent_events');
            }

            return (int) $insert->insert_id;

        }

        /**
         * @return array<string, mixed>|null
         */
        public function findById(int $eventId): ?array
        {

            if ($eventId <= 0) {
                return null;
            }

            $result = $this->query->Select('consent_events', [ 'id' => $eventId ], 1);

            if (!$result->exists || !is_array($result->row)) {
                return null;
            }

            return $result->row;

        }

        /**
         * @param array<int, string> $consentTypes
         * @return array<string, mixed>|null
         */
        public function findLastByUserAndConsentTypes(int $userId, array $consentTypes): ?array
        {

            if ($userId <= 0 || empty($consentTypes)) {
                return null;
            }

            $cleanTypes = [];

            foreach ($consentTypes as $consentType) {
                if (!is_string($consentType)) {
                    continue;
                }

                $consentType = trim($consentType);
                if ($consentType !== '') {
                    $cleanTypes[] = $consentType;
                }
            }

            if (empty($cleanTypes)) {
                return null;
            }

            $cleanTypes = array_values(array_unique($cleanTypes));
            $inList = "'";

            foreach ($cleanTypes as $consentType) {
                $inList .= $this->escape($consentType)."','";
            }

            $inList = substr($inList, 0, -2);

            $sql = "SELECT * FROM `consent_events` ";
            $sql .= "WHERE `user_id` = ".(int) $userId." ";
            $sql .= "AND `consent_type` IN ($inList) ";
            $sql .= "ORDER BY `occurred_at` DESC, `id` DESC ";
            $sql .= "LIMIT 1";

            return $this->fetchOne($sql);

        }

        /**
         * Lookup polimorfico: "dato un record sorgente
         * (table + id), restituisci tutti i consensi che ha generato".
         *
         * @return array<int, array<string, mixed>>
         */
        public function findBySubjectRef(string $subjectRefType, int $subjectRefId, int $limit = 100): array
        {

            $subjectRefType = trim($subjectRefType);

            if ($subjectRefType === '' || $subjectRefId <= 0) {
                return [];
            }

            $limit = max(1, min(1000, $limit));

            $sql = "SELECT ";
            $sql .= "ce.id, ce.user_id, ce.subject_email, ce.subject_ref_type, ce.subject_ref_id, ";
            $sql .= "ce.consent_type, ce.action, ce.occurred_at, ce.ip_address, ce.user_agent, ce.locale, ce.source, ce.ui_surface, ce.evidence_json, ce.creation, ";
            $sql .= "ld.id AS document_id, ld.doc_type AS document_type, ld.version AS document_version, ld.language_code AS document_language_code, ld.content_hash AS document_content_hash ";
            $sql .= "FROM `consent_events` ce ";
            $sql .= "LEFT JOIN `legal_documents` ld ON ld.id = ce.legal_document_id ";
            $sql .= "WHERE ce.subject_ref_type = '".$this->escape($subjectRefType)."' ";
            $sql .= "AND ce.subject_ref_id = ".(int) $subjectRefId." ";
            $sql .= "ORDER BY ce.occurred_at DESC, ce.id DESC ";
            $sql .= "LIMIT ".(int) $limit;

            return $this->fetchAll($sql);

        }

        /**
         * Storico sintetico con join al documento legale.
         *
         * @return array<int, array<string, mixed>>
         */
        public function getUserHistory(int $userId, int $limit = 100): array
        {

            if ($userId <= 0) {
                return [];
            }

            $limit = max(1, min(1000, $limit));

            $sql = "SELECT ";
            $sql .= "ce.id, ce.user_id, ce.consent_type, ce.action, ce.occurred_at, ce.ip_address, ce.user_agent, ce.locale, ce.source, ce.ui_surface, ce.evidence_json, ce.creation, ";
            $sql .= "ld.id AS document_id, ld.doc_type AS document_type, ld.version AS document_version, ld.language_code AS document_language_code, ld.content_hash AS document_content_hash ";
            $sql .= "FROM `consent_events` ce ";
            $sql .= "LEFT JOIN `legal_documents` ld ON ld.id = ce.legal_document_id ";
            $sql .= "WHERE ce.user_id = ".(int) $userId." ";
            $sql .= "ORDER BY ce.occurred_at DESC, ce.id DESC ";
            $sql .= "LIMIT ".(int) $limit;

            return $this->fetchAll($sql);

        }
    }
