<?php

    use Wonder\Consent\Service\ConsentService;

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
