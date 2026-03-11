<?php

    /**
     * Ritorna i tipi documento legale disponibili (codice => label).
     *
     * @return array<string, string>
     */
    function legalDocumentTypes(): array
    {

        $types = Wonder\Consent\LegalDocumentTypeContext::getTypes();
        $translatedTypes = [];

        foreach ($types as $code => $fallbackLabel) {

            $translationKey = "components.legal.{$code}";
            $translated = '';

            try {
                $translatedValue = __t($translationKey);
                if (is_string($translatedValue)) {
                    $translated = trim($translatedValue);
                }
            } catch (Throwable $exception) {
                $translated = '';
            }

            if ($translated === '') {
                $translated = $fallbackLabel;
            }

            $translatedTypes[$code] = (string) $translated;

        }

        return $translatedTypes;

    }

    function infoLegalDocument($value, $filter = 'id') {

        $RETURN = info('legal_documents', $filter, $value);

        if ($RETURN->exists) {

            $RETURN->render = (new Wonder\Plugin\Custom\Input\EditorBlocksRenderer())::make($RETURN->content_snapshot);
            
        }

        return $RETURN;

    }