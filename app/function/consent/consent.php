<?php

    use Wonder\Consent\Service\ConsentService;

    /**
     * Dizionario visuale eventi consenso (azione/fonte).
     *
     * @return array<string, array<string, array<string, string>>>
     */
    function consentEventDictionary(): array
    {

        static $dictionary = [
            'action' => [
                'accept' => [
                    'name' => 'Accetta',
                    'text' => 'Consenso accettato',
                    'icon' => 'bi bi-check-circle',
                    'color' => 'success'
                ],
                'reject' => [
                    'name' => 'Rifiuta',
                    'text' => 'Consenso rifiutato',
                    'icon' => 'bi bi-x-circle',
                    'color' => 'danger'
                ],
                'withdraw' => [
                    'name' => 'Revoca',
                    'text' => 'Consenso revocato',
                    'icon' => 'bi bi-arrow-counterclockwise',
                    'color' => 'warning'
                ],
            ],
            'source' => [
                'web' => [
                    'name' => 'Web',
                    'text' => 'Frontend web',
                    'icon' => 'bi bi-globe',
                    'color' => 'primary'
                ],
                'app' => [
                    'name' => 'App',
                    'text' => 'Applicazione',
                    'icon' => 'bi bi-phone',
                    'color' => 'info'
                ],
                'api' => [
                    'name' => 'API',
                    'text' => 'Integrazione API',
                    'icon' => 'bi bi-plug',
                    'color' => 'secondary'
                ],
                'admin' => [
                    'name' => 'Admin',
                    'text' => 'Backend amministrativo',
                    'icon' => 'bi bi-shield-lock',
                    'color' => 'dark'
                ],
            ],
        ];

        return $dictionary;

    }

    /**
     * Ritorna il dettaglio di action/source in modo uniforme.
     */
    function consentEventDetail(string $type, $key = null)
    {

        $dictionary = consentEventDictionary();
        $items = match ($type) {
            'action' => $dictionary['action'],
            'source' => $dictionary['source'],
            default => throw new InvalidArgumentException("Tipo dettaglio consenso non supportato: {$type}"),
        };

        return arrayDetails($items, $key);

    }

    function consentEventAction($action = null)
    {

        return consentEventDetail('action', $action);

    }

    function consentEventSource($source = null)
    {

        return consentEventDetail('source', $source);

    }


    /**
     * Istanza singleton del servizio consensi.
     */
    function consentService(): ConsentService
    {

        static $service = null;

        if ($service === null) {
            $service = new ConsentService();
        }

        return $service;

    }

    /**
     * Registra consensi da payload form (checkbox + document id).
     *
     * @param int $userId
     * @param array<string, mixed> $input
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    function registerUserConsentsFromPayload(int $userId, array $input, array $context = []): array
    {

        return consentService()->registerBaseConsents($userId, $input, $context);

    }

    /**
     * Registra consenso da singolo documento legale.
     *
     * @param int $userId
     * @param int $documentId
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    function registerUserConsentByDocumentId(int $userId, int $documentId, array $context = []): array
    {

        return consentService()->registerConsentByDocumentId($userId, $documentId, $context);

    }

    /**
     * Registra i consensi utente.
     *
     * @param int $userId
     * @param array<string, mixed>|int $input
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    function registerUserConsents(int $userId, array|int $input, array $context = []): array
    {

        if (is_int($input)) {
            return registerUserConsentByDocumentId($userId, $input, $context);
        }

        return registerUserConsentsFromPayload($userId, $input, $context);

    }

    /**
     * Stato corrente e storico sintetico consensi utente.
     *
     * @param int $userId
     * @param int $historyLimit
     * @return array<string, mixed>
     */
    function getUserConsentsSnapshot(int $userId, int $historyLimit = 100): array
    {

        return consentService()->getUserConsents($userId, $historyLimit);

    }

    /**
     * Lookup polimorfico consenso ↔ record sorgente.
     *
     *   $events = consentsForRecord('requests', $requestId);
     *
     * Comodo nelle view del backend per mostrare la sezione "Consensi
     * raccolti" sotto un record (es. una pagina dettaglio richiesta che
     * elenca i `consent_events.subject_ref_type='requests'` correlati).
     *
     * @return array<int, array<string, mixed>>
     */
    function consentsForRecord(string $subjectRefType, int $subjectRefId, int $limit = 100): array
    {

        return (new \Wonder\Consent\Repository\ConsentEventRepository(null))
            ->findBySubjectRef($subjectRefType, $subjectRefId, $limit);

    }

    /**
     * Registra i consensi di un "lead" identificato dall'email (form
     * pubblici: contatto, newsletter, lead magnet). Mirror procedurale
     * di `ConsentService::registerLeadConsents()`.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    function registerLeadConsents(string $email, array $input, array $context = []): array
    {

        return consentService()->registerLeadConsents($email, $input, $context);

    }

    /**
     * Hook generico chiamato dai Resource controller (Backend + API) dopo
     * lo store: se `$values` contiene almeno un checkbox `accept_<doc>`,
     * lo registra in `consent_events` instradando al pipeline corretto:
     *
     *  - user loggato (`$_SESSION['user_id'] > 0`)   → `registerUserConsents`
     *  - lead anonimo con `$values['email']` valida → `registerLeadConsents`
     *  - nessuno dei due                              → log silente, no throw
     *
     * Non lancia mai eccezioni che possano abbattere lo store: una
     * registrazione consenso fallita è grave per il GDPR ma non deve
     * rovesciare il submit del form. Viene loggata via `__log()`.
     *
     * `$context['ui_surface']` identifica il punto di raccolta (es. nome
     * della Resource: `requests/store`, `users/signup`, ...).
     *
     * @param array<string, mixed> $values
     * @param array<string, mixed> $context
     */
    function recordResourceConsents(array $values, array $context = []): void
    {

        $hasConsentPayload = false;

        foreach ($values as $key => $_) {
            if (is_string($key) && str_starts_with($key, 'accept_')) {
                $hasConsentPayload = true;
                break;
            }
        }

        if (!$hasConsentPayload) {
            return;
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);

        try {

            if ($userId > 0) {
                registerUserConsents($userId, $values, $context);
                return;
            }

            $email = trim((string) ($values['email'] ?? ''));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                registerLeadConsents($email, $values, $context);
                return;
            }

            if (function_exists('__log')) {
                __log(
                    new RuntimeException('Consenso ricevuto ma nessun subject (user_id/email) disponibile'),
                    'consent',
                    'record_resource_consents_no_subject'
                );
            }

        } catch (Throwable $exception) {

            if (function_exists('__log')) {
                __log($exception, 'consent', 'record_resource_consents_failed');
            }

        }

    }
