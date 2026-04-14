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
