<?php

    namespace Wonder\Consent;

    /**
     * Registro tipi documento legale estendibile a runtime.
     *
     * Uso tipico:
     * LegalDocumentTypeContext::addType('refund_policy');
     */
    class LegalDocumentTypeContext
    {
        /**
         * @var array<string, string>
         */
        private static array $types = [];
        private static bool $initialized = false;

        /**
         * Inizializza il registro tipi.
         * I default vengono caricati dal bootstrap service/consent.php.
         */
        private static function boot(): void
        {

            if (self::$initialized) {
                return;
            }

            self::$initialized = true;
            self::$types = [];

        }

        /**
         * Aggiunge un tipo documento.
         */
        public static function addType(string $code, ?string $label = null): self
        {

            self::boot();

            $code = ConsentDictionary::normalizeDocumentType($code);

            if ($code === '') {
                return new self();
            }

            if ($label === null || trim($label) === '') {
                $label = self::buildLabelFromCode($code);
            }

            self::$types[$code] = trim($label);

            return new self();

        }

        /**
         * Aggiunge più tipi documento in un colpo solo.
         *
         * @param array<string, string> $types
         */
        public static function addTypes(array $types): self
        {

            self::boot();

            foreach ($types as $code => $label) {
                self::addType((string) $code, (string) $label);
            }

            return new self();

        }

        /**
         * @return array<string, string>
         */
        public static function getTypes(): array
        {

            self::boot();

            return self::$types;

        }

        /**
         * @return array<int, string>
         */
        public static function getCodes(): array
        {

            self::boot();

            return array_keys(self::$types);

        }

        public static function hasType(string $code): bool
        {

            self::boot();

            $code = ConsentDictionary::normalizeDocumentType($code);

            return isset(self::$types[$code]);

        }

        private static function buildLabelFromCode(string $code): string
        {

            $label = str_replace([ '_', '-' ], ' ', $code);

            return ucwords($label);

        }
    }
