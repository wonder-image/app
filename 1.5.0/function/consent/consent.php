<?php

    use Wonder\Consent\Service\ConsentService;

    $CONSENT_EVENTS_ACTION = [
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
        ]
    ];

    $CONSENT_EVENTS_SOURCE = [
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
        ]
    ];

    function consentEventAction($action = null)
    {

        global $CONSENT_EVENTS_ACTION;

        return arrayDetails($CONSENT_EVENTS_ACTION, $action);

    }

    function consentEventSource($source = null)
    {

        global $CONSENT_EVENTS_SOURCE;

        return arrayDetails($CONSENT_EVENTS_SOURCE, $source);

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
     * Registra i consensi utente.
     *
     * @param int $userId
     * @param array<string, mixed>|int $input
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    function registerUserConsents(int $userId, array|int $input, array $context = []): array
    {

        // Shortcut: registerUserConsents($userId, $documentId)
        if (is_int($input)) {
            return consentService()->registerConsentByDocumentId($userId, $input, $context);
        }

        return consentService()->registerBaseConsents($userId, $input, $context);

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
