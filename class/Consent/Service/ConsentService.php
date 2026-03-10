<?php

    namespace Wonder\Consent\Service;

    use mysqli;
    use Throwable;
    use Wonder\Consent\ConsentDictionary;
    use Wonder\Consent\ConsentException;
    use Wonder\Consent\Repository\ConsentEventRepository;
    use Wonder\Consent\Repository\LegalDocumentRepository;
    use Wonder\Consent\Repository\MarketingOptInTokenRepository;
    use Wonder\Consent\Repository\UserConsentStateRepository;
    use Wonder\Sql\Query;

    /**
     * Servizio applicativo per gestione consensi GDPR.
     */
    class ConsentService
    {
        private mysqli $mysqli;
        private LegalDocumentRepository $legalDocumentRepository;
        private ConsentEventRepository $consentEventRepository;
        private UserConsentStateRepository $userConsentStateRepository;
        private MarketingOptInTokenRepository $marketingOptInTokenRepository;

        public function __construct(?mysqli $mysqli = null)
        {

            $query = new Query($mysqli);

            $this->mysqli = $query->mysqli;
            $this->legalDocumentRepository = new LegalDocumentRepository($this->mysqli);
            $this->consentEventRepository = new ConsentEventRepository($this->mysqli);
            $this->userConsentStateRepository = new UserConsentStateRepository($this->mysqli);
            $this->marketingOptInTokenRepository = new MarketingOptInTokenRepository($this->mysqli);

        }

        /**
         * Registra i consensi base in fase signup.
         *
         * @param int $userId
         * @param array<string, mixed> $input
         * @param array<string, mixed> $context
         * @return array<string, mixed>
         * @throws ConsentException
         */
        public function registerBaseConsents(int $userId, array $input, array $context = []): array
        {

            if ($userId <= 0) {
                throw new ConsentException('user_id non valido');
            }

            $acceptTerms = $this->toBool($input['accept_terms'] ?? false);
            $ackPrivacy = $this->toBool($input['ack_privacy'] ?? false);

            if (!$acceptTerms) {
                throw new ConsentException('L’accettazione dei termini è obbligatoria');
            }

            if (!$ackPrivacy) {
                throw new ConsentException('La presa visione privacy è obbligatoria');
            }

            $termsDocumentId = (int) ($input['terms_document_id'] ?? 0);
            $privacyDocumentId = (int) ($input['privacy_document_id'] ?? 0);

            $this->assertDocumentType($termsDocumentId, ConsentDictionary::DOC_TYPE_TERMS);
            $this->assertDocumentType($privacyDocumentId, ConsentDictionary::DOC_TYPE_PRIVACY_POLICY);

            $ctx = $this->normalizeContext($context);
            $now = $this->now();

            $acceptMarketing = $this->toBool($input['accept_marketing'] ?? false);
            $hasMarketingFlag = array_key_exists('accept_marketing', $input);
            $tokenResult = null;
            $eventIds = [];

            $this->beginTransaction();

            try {

                $termsEventId = $this->createEventAndState(
                    $userId,
                    ConsentDictionary::CONSENT_TYPE_TERMS_ACCEPT,
                    ConsentDictionary::ACTION_ACCEPT,
                    $termsDocumentId,
                    $ctx,
                    null,
                    $now
                );

                $privacyEventId = $this->createEventAndState(
                    $userId,
                    ConsentDictionary::CONSENT_TYPE_PRIVACY_ACK,
                    ConsentDictionary::ACTION_ACCEPT,
                    $privacyDocumentId,
                    $ctx,
                    null,
                    $now
                );

                $eventIds['terms_accept'] = $termsEventId;
                $eventIds['privacy_ack'] = $privacyEventId;

                if ($acceptMarketing) {

                    $this->assertUserEmailVerifiedForMarketing($userId);

                    $marketingDocumentId = (int) ($input['marketing_document_id'] ?? 0);
                    $this->assertDocumentType($marketingDocumentId, ConsentDictionary::DOC_TYPE_MARKETING);

                    $marketingEventId = $this->createEventAndState(
                        $userId,
                        ConsentDictionary::CONSENT_TYPE_MARKETING_OPTIN,
                        ConsentDictionary::ACTION_ACCEPT,
                        $marketingDocumentId,
                        $ctx,
                        ConsentDictionary::STATUS_PENDING,
                        $now
                    );

                    $eventIds['marketing_optin'] = $marketingEventId;

                    $tokenResult = $this->createMarketingToken(
                        $userId,
                        $marketingEventId,
                        (int) ($input['double_optin_ttl_hours'] ?? 48),
                        $now
                    );

                } elseif ($hasMarketingFlag) {

                    $marketingDocumentId = (int) ($input['marketing_document_id'] ?? 0);
                    if ($marketingDocumentId > 0) {
                        $this->assertDocumentType($marketingDocumentId, ConsentDictionary::DOC_TYPE_MARKETING);
                    } else {
                        $marketingDocumentId = null;
                    }

                    $marketingRejectEventId = $this->createEventAndState(
                        $userId,
                        ConsentDictionary::CONSENT_TYPE_MARKETING_OPTIN,
                        ConsentDictionary::ACTION_REJECT,
                        $marketingDocumentId,
                        $ctx,
                        null,
                        $now
                    );

                    $eventIds['marketing_optin'] = $marketingRejectEventId;

                }

                $this->commit();

                return [
                    'user_id' => $userId,
                    'events' => $eventIds,
                    'double_opt_in' => $tokenResult,
                ];

            } catch (Throwable $exception) {

                $this->rollback();

                if ($exception instanceof ConsentException) {
                    throw $exception;
                }

                throw new ConsentException('Errore durante la registrazione dei consensi: '.$exception->getMessage());

            }
        }

        /**
         * Registra consenso partendo da un singolo documento legale.
         *
         * @param int $userId
         * @param int $documentId
         * @param array<string, mixed> $context
         * @return array<string, mixed>
         * @throws ConsentException
         */
        public function registerConsentByDocumentId(int $userId, int $documentId, array $context = []): array
        {

            if ($userId <= 0) {
                throw new ConsentException('user_id non valido');
            }

            if ($documentId <= 0) {
                throw new ConsentException('document_id non valido');
            }

            $document = $this->legalDocumentRepository->findById($documentId);

            if ($document === null) {
                throw new ConsentException("Documento legale non trovato: id {$documentId}");
            }

            $docType = (string) ($document['doc_type'] ?? '');
            $ctx = $this->normalizeContext($context);
            $now = $this->now();

            $this->beginTransaction();

            try {

                $tokenResult = null;
                $consentType = '';
                $statusOverride = null;

                switch ($docType) {
                    case ConsentDictionary::DOC_TYPE_TERMS:
                        $consentType = ConsentDictionary::CONSENT_TYPE_TERMS_ACCEPT;
                        break;
                    case ConsentDictionary::DOC_TYPE_PRIVACY_POLICY:
                        $consentType = ConsentDictionary::CONSENT_TYPE_PRIVACY_ACK;
                        break;
                    case ConsentDictionary::DOC_TYPE_MARKETING:
                        $this->assertUserEmailVerifiedForMarketing($userId);
                        $consentType = ConsentDictionary::CONSENT_TYPE_MARKETING_OPTIN;
                        $statusOverride = ConsentDictionary::STATUS_PENDING;
                        break;
                    default:
                        throw new ConsentException("Tipo documento non gestito per registrazione diretta: {$docType}");
                }

                $eventId = $this->createEventAndState(
                    $userId,
                    $consentType,
                    ConsentDictionary::ACTION_ACCEPT,
                    $documentId,
                    $ctx,
                    $statusOverride,
                    $now
                );

                if ($docType === ConsentDictionary::DOC_TYPE_MARKETING) {
                    $tokenResult = $this->createMarketingToken(
                        $userId,
                        $eventId,
                        (int) ($context['double_optin_ttl_hours'] ?? 48),
                        $now
                    );
                }

                $this->commit();

                return [
                    'user_id' => $userId,
                    'document_id' => $documentId,
                    'doc_type' => $docType,
                    'consent_event_id' => $eventId,
                    'double_opt_in' => $tokenResult,
                ];

            } catch (Throwable $exception) {

                $this->rollback();

                if ($exception instanceof ConsentException) {
                    throw $exception;
                }

                throw new ConsentException('Errore durante registrazione consenso documento: '.$exception->getMessage());

            }
        }

        /**
         * Conferma il double opt-in marketing.
         *
         * @param string $token
         * @param array<string, mixed> $context
         * @return array<string, mixed>
         * @throws ConsentException
         */
        public function confirmMarketingOptIn(string $token, array $context = []): array
        {

            $token = trim($token);

            if ($token === '') {
                throw new ConsentException('Token marketing mancante');
            }

            $ctx = $this->normalizeContext($context);
            $now = $this->now();

            $this->beginTransaction();

            try {

                $tokenRow = $this->marketingOptInTokenRepository->findByTokenForUpdate($token);

                if ($tokenRow === null) {
                    throw new ConsentException('Token marketing non trovato');
                }

                if (!empty($tokenRow['revoked_at'])) {
                    throw new ConsentException('Token marketing revocato');
                }

                if (!empty($tokenRow['confirmed_at'])) {
                    throw new ConsentException('Token marketing già confermato');
                }

                $expiresAt = (string) ($tokenRow['expires_at'] ?? '');
                if ($expiresAt === '' || strtotime($expiresAt) < strtotime($now)) {
                    throw new ConsentException('Token marketing scaduto');
                }

                $optInEventId = (int) ($tokenRow['consent_event_id'] ?? 0);
                $optInEvent = $this->consentEventRepository->findById($optInEventId);

                if ($optInEvent === null) {
                    throw new ConsentException('Evento marketing_optin non trovato');
                }

                if (($optInEvent['consent_type'] ?? '') !== ConsentDictionary::CONSENT_TYPE_MARKETING_OPTIN) {
                    throw new ConsentException('Il token non è associato a un evento marketing_optin valido');
                }

                $userId = (int) ($optInEvent['user_id'] ?? 0);
                $legalDocumentId = isset($optInEvent['legal_document_id']) ? (int) $optInEvent['legal_document_id'] : null;

                $ctx['evidence_json']['double_opt_in_token_id'] = (int) $tokenRow['id'];

                $confirmEventId = $this->createEventAndState(
                    $userId,
                    ConsentDictionary::CONSENT_TYPE_MARKETING_OPTIN_CONFIRMED,
                    ConsentDictionary::ACTION_ACCEPT,
                    $legalDocumentId,
                    $ctx,
                    ConsentDictionary::STATUS_ACCEPTED,
                    $now
                );

                # Aggiorno anche lo stato opt-in base: da pending/rejected a accepted.
                $this->userConsentStateRepository->upsert(
                    $userId,
                    ConsentDictionary::CONSENT_TYPE_MARKETING_OPTIN,
                    ConsentDictionary::STATUS_ACCEPTED,
                    $legalDocumentId,
                    $confirmEventId,
                    $now
                );

                $this->marketingOptInTokenRepository->markConfirmed((int) $tokenRow['id'], $now);

                $this->commit();

                return [
                    'user_id' => $userId,
                    'token_id' => (int) $tokenRow['id'],
                    'consent_event_id' => $confirmEventId,
                    'confirmed_at' => $now,
                ];

            } catch (Throwable $exception) {

                $this->rollback();

                if ($exception instanceof ConsentException) {
                    throw $exception;
                }

                throw new ConsentException('Errore durante conferma marketing: '.$exception->getMessage());

            }
        }

        /**
         * Revoca il consenso marketing.
         *
         * @param int $userId
         * @param array<string, mixed> $context
         * @return array<string, mixed>
         * @throws ConsentException
         */
        public function withdrawMarketing(int $userId, array $context = []): array
        {

            if ($userId <= 0) {
                throw new ConsentException('user_id non valido');
            }

            $ctx = $this->normalizeContext($context);
            $now = $this->now();

            $this->beginTransaction();

            try {

                $lastMarketingEvent = $this->consentEventRepository->findLastByUserAndConsentTypes(
                    $userId,
                    [
                        ConsentDictionary::CONSENT_TYPE_MARKETING_OPTIN_CONFIRMED,
                        ConsentDictionary::CONSENT_TYPE_MARKETING_OPTIN
                    ]
                );

                $legalDocumentId = null;

                if (is_array($lastMarketingEvent) && isset($lastMarketingEvent['legal_document_id'])) {
                    $legalDocumentId = (int) $lastMarketingEvent['legal_document_id'];
                }

                $withdrawEventId = $this->createEventAndState(
                    $userId,
                    ConsentDictionary::CONSENT_TYPE_MARKETING_WITHDRAWN,
                    ConsentDictionary::ACTION_WITHDRAW,
                    $legalDocumentId,
                    $ctx,
                    ConsentDictionary::STATUS_WITHDRAWN,
                    $now
                );

                # Coerenza stato marketing: non più attivo dopo revoca.
                $this->userConsentStateRepository->upsert(
                    $userId,
                    ConsentDictionary::CONSENT_TYPE_MARKETING_OPTIN_CONFIRMED,
                    ConsentDictionary::STATUS_WITHDRAWN,
                    $legalDocumentId,
                    $withdrawEventId,
                    $now
                );

                $this->userConsentStateRepository->upsert(
                    $userId,
                    ConsentDictionary::CONSENT_TYPE_MARKETING_OPTIN,
                    ConsentDictionary::STATUS_WITHDRAWN,
                    $legalDocumentId,
                    $withdrawEventId,
                    $now
                );

                $this->marketingOptInTokenRepository->revokeOpenByUserId($userId, $now);

                $this->commit();

                return [
                    'user_id' => $userId,
                    'consent_event_id' => $withdrawEventId,
                    'withdrawn_at' => $now,
                ];

            } catch (Throwable $exception) {

                $this->rollback();

                if ($exception instanceof ConsentException) {
                    throw $exception;
                }

                throw new ConsentException('Errore durante revoca marketing: '.$exception->getMessage());

            }
        }

        /**
         * Stato corrente + storico sintetico consensi.
         *
         * @return array<string, mixed>
         */
        public function getUserConsents(int $userId, int $historyLimit = 100): array
        {

            $currentState = $this->userConsentStateRepository->getByUserId($userId);
            $history = $this->consentEventRepository->getUserHistory($userId, $historyLimit);

            $marketingActive = false;

            foreach ($currentState as $stateRow) {
                if (
                    ($stateRow['consent_type'] ?? '') === ConsentDictionary::CONSENT_TYPE_MARKETING_OPTIN_CONFIRMED &&
                    ($stateRow['current_status'] ?? '') === ConsentDictionary::STATUS_ACCEPTED
                ) {
                    $marketingActive = true;
                    break;
                }
            }

            return [
                'user_id' => $userId,
                'marketing_active' => $marketingActive,
                'current_state' => $currentState,
                'history' => $history,
            ];

        }

        /**
         * @param int $userId
         * @param string $consentType
         * @param string $action
         * @param int|null $legalDocumentId
         * @param array<string, mixed> $context
         * @param string|null $statusOverride
         * @param string $occurredAt
         * @return int
         * @throws ConsentException
         */
        private function createEventAndState(
            int $userId,
            string $consentType,
            string $action,
            ?int $legalDocumentId,
            array $context,
            ?string $statusOverride,
            string $occurredAt
        ): int {

            $eventId = $this->consentEventRepository->create([
                'user_id' => $userId,
                'consent_type' => $consentType,
                'action' => $action,
                'legal_document_id' => $legalDocumentId,
                'occurred_at' => $occurredAt,
                'ip_address' => (string) ($context['ip_address'] ?? ''),
                'user_agent' => (string) ($context['user_agent'] ?? ''),
                'locale' => (string) ($context['locale'] ?? 'it'),
                'source' => (string) ($context['source'] ?? ConsentDictionary::SOURCE_WEB),
                'ui_surface' => (string) ($context['ui_surface'] ?? ''),
                'evidence_json' => $context['evidence_json'] ?? null,
                'created_at' => $occurredAt,
            ]);

            $status = $statusOverride ?? ConsentDictionary::statusFromAction($action);

            $this->userConsentStateRepository->upsert(
                $userId,
                $consentType,
                $status,
                $legalDocumentId,
                $eventId,
                $occurredAt
            );

            return $eventId;

        }

        /**
         * @param int $userId
         * @param int $consentEventId
         * @param int $ttlHours
         * @param string $createdAt
         * @return array<string, mixed>
         * @throws ConsentException
         */
        private function createMarketingToken(int $userId, int $consentEventId, int $ttlHours, string $createdAt): array
        {

            if ($ttlHours <= 0) {
                $ttlHours = 48;
            }

            $token = $this->generateToken();
            $expiresAt = date('Y-m-d H:i:s', strtotime($createdAt.' +'.$ttlHours.' hours'));

            $tokenId = $this->marketingOptInTokenRepository->create(
                $userId,
                $consentEventId,
                $token,
                $expiresAt,
                $createdAt
            );

            return [
                'token_id' => $tokenId,
                'token' => $token,
                'expires_at' => $expiresAt,
            ];

        }

        /**
         * @param int $documentId
         * @param string $expectedType
         * @throws ConsentException
         */
        private function assertDocumentType(int $documentId, string $expectedType): void
        {

            if ($documentId <= 0) {
                throw new ConsentException("Documento legale mancante per tipo {$expectedType}");
            }

            $document = $this->legalDocumentRepository->findById($documentId);

            if ($document === null) {
                throw new ConsentException("Documento legale non trovato: id {$documentId}");
            }

            $actualType = (string) ($document['doc_type'] ?? '');

            if ($actualType !== $expectedType) {
                throw new ConsentException("Documento legale {$documentId} non coerente: atteso {$expectedType}, trovato {$actualType}");
            }

        }

        /**
         * Marketing consent consentito solo se email utente verificata.
         *
         * @throws ConsentException
         */
        private function assertUserEmailVerifiedForMarketing(int $userId): void
        {

            if ($userId <= 0) {
                throw new ConsentException('user_id non valido per verifica email');
            }

            $row = $this->fetchUserVerificationRow($userId);

            if ($row === null) {
                throw new ConsentException("Utente non trovato: {$userId}");
            }

            $verified = $row['email_verified'] ?? 0;
            $verifiedAt = (string) ($row['email_verified_at'] ?? '');

            $isVerified = ($verified == '1' || $verified == 1 || $verified === true || $verified === 'true');

            if (!$isVerified && $verifiedAt !== '' && $verifiedAt !== '0000-00-00 00:00:00') {
                $isVerified = true;
            }

            if (!$isVerified) {
                throw new ConsentException('Per accettare il marketing devi prima confermare la tua email.');
            }

        }

        /**
         * @return array<string, mixed>|null
         */
        private function fetchUserVerificationRow(int $userId): ?array
        {

            $sql = "SELECT `id`, `email_verified`, `email_verified_at` ";
            $sql .= "FROM `user` WHERE `id` = ".(int) $userId." LIMIT 1";

            $result = $this->mysqli->query($sql);

            if ($result === false || $result->num_rows === 0) {
                return null;
            }

            $row = $result->fetch_assoc();

            return is_array($row) ? $row : null;

        }

        /**
         * @param array<string, mixed> $context
         * @return array<string, mixed>
         */
        private function normalizeContext(array $context): array
        {

            $source = (string) ($context['source'] ?? ConsentDictionary::SOURCE_WEB);

            if (!in_array($source, ConsentDictionary::sources(), true)) {
                $source = ConsentDictionary::SOURCE_WEB;
            }

            $locale = (string) ($context['locale'] ?? '');

            if ($locale === '' && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $locale = (string) $_SERVER['HTTP_ACCEPT_LANGUAGE'];
            }

            $locale = ConsentDictionary::normalizeLanguageCode($locale, 'it');

            $evidence = $context['evidence_json'] ?? [];
            if (!is_array($evidence)) {
                $evidence = [];
            }

            return [
                'ip_address' => (string) ($context['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '')),
                'user_agent' => (string) ($context['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')),
                'locale' => $locale,
                'source' => $source,
                'ui_surface' => (string) ($context['ui_surface'] ?? ''),
                'evidence_json' => $evidence,
            ];

        }

        private function toBool(mixed $value): bool
        {

            if (is_bool($value)) {
                return $value;
            }

            if (is_int($value)) {
                return $value === 1;
            }

            if (is_string($value)) {
                $value = strtolower(trim($value));
                return in_array($value, [ '1', 'true', 'yes', 'on' ], true);
            }

            return false;

        }

        private function generateToken(): string
        {

            if (function_exists('random_bytes')) {
                return bin2hex(random_bytes(32));
            }

            return sha1(uniqid((string) mt_rand(), true));

        }

        private function beginTransaction(): void
        {

            $this->mysqli->begin_transaction();

        }

        private function commit(): void
        {

            $this->mysqli->commit();

        }

        private function rollback(): void
        {

            $this->mysqli->rollback();

        }

        private function now(): string
        {

            return date('Y-m-d H:i:s');

        }
    }
