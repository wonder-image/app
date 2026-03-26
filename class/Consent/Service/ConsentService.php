<?php

    namespace Wonder\Consent\Service;

    use mysqli;
    use Throwable;
    use Wonder\Consent\ConsentDictionary;
    use Wonder\Consent\ConsentException;
    use Wonder\Consent\Repository\ConsentEventRepository;
    use Wonder\Consent\Repository\LegalDocumentRepository;
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

        public function __construct(?mysqli $mysqli = null)
        {

            $query = new Query($mysqli);

            $this->mysqli = $query->mysqli;
            $this->legalDocumentRepository = new LegalDocumentRepository($this->mysqli);
            $this->consentEventRepository = new ConsentEventRepository($this->mysqli);
            $this->userConsentStateRepository = new UserConsentStateRepository($this->mysqli);

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

            $documentSelections = $this->extractDocumentSelectionsFromInput($input);

            if (empty($documentSelections)) {
                throw new ConsentException('Nessun documento consenso trovato nel payload');
            }

            $requiredDocumentTypes = $this->normalizeDocumentTypeList($context['required_document_types'] ?? []);
            $this->assertRequiredDocumentSelections($requiredDocumentTypes, $documentSelections);

            $ctx = $this->normalizeContext($context);
            $now = $this->now();
            $eventIds = [];

            $this->beginTransaction();

            try {

                foreach ($documentSelections as $docType => $accepted) {

                    $documentId = $this->extractDocumentIdFromInput($input, $docType);

                    if ($documentId <= 0) {

                        if ($accepted || in_array($docType, $requiredDocumentTypes, true)) {
                            throw new ConsentException("Documento legale mancante per tipo {$docType}");
                        }

                        continue;

                    }

                    $this->assertDocumentType($documentId, $docType);

                    $consentType = ConsentDictionary::consentTypeFromDocumentType($docType);

                    if ($consentType === '') {
                        throw new ConsentException("Impossibile risolvere consent_type per documento {$docType}");
                    }

                    $action = $accepted ? ConsentDictionary::ACTION_ACCEPT : ConsentDictionary::ACTION_REJECT;
                    $statusOverride = null;

                    $eventId = $this->createEventAndState(
                        $userId,
                        $consentType,
                        $action,
                        $documentId,
                        $ctx,
                        $statusOverride,
                        $now
                    );

                    $eventIds[$docType] = $eventId;
                }

                if (empty($eventIds)) {
                    throw new ConsentException('Nessun evento consenso generato dal payload');
                }

                $this->commit();

                return [
                    'user_id' => $userId,
                    'events' => $eventIds,
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

                $consentType = ConsentDictionary::consentTypeFromDocumentType($docType);

                if ($consentType === '') {
                    throw new ConsentException("Impossibile risolvere consent_type per documento {$docType}");
                }

                $eventId = $this->createEventAndState(
                    $userId,
                    $consentType,
                    ConsentDictionary::ACTION_ACCEPT,
                    $documentId,
                    $ctx,
                    null,
                    $now
                );

                $this->commit();

                return [
                    'user_id' => $userId,
                    'document_id' => $documentId,
                    'doc_type' => $docType,
                    'consent_event_id' => $eventId,
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
         * Stato corrente + storico sintetico consensi.
         *
         * @return array<string, mixed>
         */
        public function getUserConsents(int $userId, int $historyLimit = 100): array
        {

            $currentState = $this->userConsentStateRepository->getByUserId($userId);
            $history = $this->consentEventRepository->getUserHistory($userId, $historyLimit);

            return [
                'user_id' => $userId,
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
                'creation' => $occurredAt,
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
         * @param int $documentId
         * @param string $expectedType
         * @throws ConsentException
         */
        private function assertDocumentType(int $documentId, string $expectedType): void
        {

            if ($documentId <= 0) {
                throw new ConsentException("Documento legale mancante per tipo {$expectedType}");
            }

            $expectedType = ConsentDictionary::normalizeDocumentType($expectedType);

            $document = $this->legalDocumentRepository->findById($documentId);

            if ($document === null) {
                throw new ConsentException("Documento legale non trovato: id {$documentId}");
            }

            $actualType = ConsentDictionary::normalizeDocumentType((string) ($document['doc_type'] ?? ''));

            if ($actualType !== $expectedType) {
                throw new ConsentException("Documento legale {$documentId} non coerente: atteso {$expectedType}, trovato {$actualType}");
            }

        }

        /**
         * Estrae l'id documento dal payload form usando la convenzione:
         * - checkbox: accept_<doc_type>
         * - hidden id: <doc_type>_id
         */
        private function extractDocumentIdFromInput(array $input, string $docType): int
        {

            $docType = ConsentDictionary::normalizeDocumentType($docType);

            if ($docType === '') {
                return 0;
            }

            $field = $docType.'_id';

            return (int) ($input[$field] ?? 0);

        }

        /**
         * Estrae dal payload i checkbox documento nel formato accept_<doc_type>.
         *
         * @param array<string, mixed> $input
         * @return array<string, bool>
         */
        private function extractDocumentSelectionsFromInput(array $input): array
        {

            $selections = [];

            foreach ($input as $key => $value) {

                if (!is_string($key) || strpos($key, 'accept_') !== 0) {
                    continue;
                }

                $docType = ConsentDictionary::normalizeDocumentType(substr($key, 7));

                if ($docType === '') {
                    continue;
                }

                $selections[$docType] = $this->toBool($value);

            }

            return $selections;

        }

        /**
         * @param mixed $types
         * @return array<int, string>
         */
        private function normalizeDocumentTypeList(mixed $types): array
        {

            if (!is_array($types)) {
                return [];
            }

            $normalized = [];

            foreach ($types as $type) {

                if (!is_string($type)) {
                    continue;
                }

                $docType = ConsentDictionary::normalizeDocumentType($type);

                if ($docType !== '') {
                    $normalized[] = $docType;
                }

            }

            return array_values(array_unique($normalized));

        }

        /**
         * Verifica che i documenti obbligatori risultino accettati.
         *
         * @param array<int, string> $requiredDocumentTypes
         * @param array<string, bool> $selections
         */
        private function assertRequiredDocumentSelections(array $requiredDocumentTypes, array $selections): void
        {

            foreach ($requiredDocumentTypes as $docType) {

                if (!isset($selections[$docType]) || $selections[$docType] !== true) {
                    throw new ConsentException("Il consenso al documento {$docType} è obbligatorio");
                }

            }

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

            if (is_array($value)) {

                foreach ($value as $item) {
                    if ($this->toBool($item)) {
                        return true;
                    }
                }

                return false;

            }

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
