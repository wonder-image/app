<?php

    namespace Wonder\Consent;

    /**
     * Dizionario centralizzato dei valori consentiti
     * per documenti, consensi, azioni, stati e sorgenti.
     */
    class ConsentDictionary
    {
        public const DOC_TYPE_PRIVACY_POLICY = 'privacy_policy';
        public const DOC_TYPE_TERMS = 'terms';
        public const DOC_TYPE_COOKIE_POLICY = 'cookie_policy';
        public const DOC_TYPE_MARKETING = 'marketing';

        public const CONSENT_TYPE_PRIVACY_ACK = 'privacy_ack';
        public const CONSENT_TYPE_TERMS_ACCEPT = 'terms_accept';
        public const CONSENT_TYPE_MARKETING_OPTIN = 'marketing_optin';
        public const CONSENT_TYPE_MARKETING_OPTIN_CONFIRMED = 'marketing_optin_confirmed';
        public const CONSENT_TYPE_MARKETING_WITHDRAWN = 'marketing_withdrawn';

        public const ACTION_ACCEPT = 'accept';
        public const ACTION_REJECT = 'reject';
        public const ACTION_WITHDRAW = 'withdraw';

        public const STATUS_ACCEPTED = 'accepted';
        public const STATUS_REJECTED = 'rejected';
        public const STATUS_WITHDRAWN = 'withdrawn';
        public const STATUS_PENDING = 'pending';

        public const SOURCE_WEB = 'web';
        public const SOURCE_APP = 'app';
        public const SOURCE_API = 'api';
        public const SOURCE_ADMIN = 'admin';

        public static function documentTypes(): array
        {

            return [
                self::DOC_TYPE_PRIVACY_POLICY,
                self::DOC_TYPE_TERMS,
                self::DOC_TYPE_COOKIE_POLICY,
                self::DOC_TYPE_MARKETING,
            ];

        }

        public static function consentTypes(): array
        {

            return [
                self::CONSENT_TYPE_PRIVACY_ACK,
                self::CONSENT_TYPE_TERMS_ACCEPT,
                self::CONSENT_TYPE_MARKETING_OPTIN,
                self::CONSENT_TYPE_MARKETING_OPTIN_CONFIRMED,
                self::CONSENT_TYPE_MARKETING_WITHDRAWN,
            ];

        }

        public static function actions(): array
        {

            return [
                self::ACTION_ACCEPT,
                self::ACTION_REJECT,
                self::ACTION_WITHDRAW,
            ];

        }

        public static function statuses(): array
        {

            return [
                self::STATUS_ACCEPTED,
                self::STATUS_REJECTED,
                self::STATUS_WITHDRAWN,
                self::STATUS_PENDING,
            ];

        }

        public static function sources(): array
        {

            return [
                self::SOURCE_WEB,
                self::SOURCE_APP,
                self::SOURCE_API,
                self::SOURCE_ADMIN,
            ];

        }

        /**
         * Normalizza il codice lingua su 2 lettere (es. it, de, en).
         */
        public static function normalizeLanguageCode(string $languageCode, string $fallback = 'it'): string
        {

            $languageCode = strtolower(trim($languageCode));
            $languageCode = preg_replace('/[^a-z]/', '', $languageCode) ?? '';
            $languageCode = substr($languageCode, 0, 2);

            if ($languageCode === '') {
                return $fallback;
            }

            return $languageCode;

        }

        /**
         * Valida che un valore appartenga a una whitelist.
         *
         * @throws ConsentException
         */
        public static function assertAllowed(string $value, array $allowed, string $label): void
        {

            if (!in_array($value, $allowed, true)) {
                throw new ConsentException("Valore non valido per {$label}: {$value}");
            }

        }

        /**
         * Stato derivato dall'azione evento.
         *
         * @throws ConsentException
         */
        public static function statusFromAction(string $action): string
        {

            switch ($action) {
                case self::ACTION_ACCEPT:
                    return self::STATUS_ACCEPTED;
                case self::ACTION_REJECT:
                    return self::STATUS_REJECTED;
                case self::ACTION_WITHDRAW:
                    return self::STATUS_WITHDRAWN;
                default:
                    throw new ConsentException("Azione non valida: {$action}");
            }

        }
    }

