<?php

    use Wonder\Consent\LegalDocumentTypeContext;

    # Inizializzo i tipi documento legale di default
        LegalDocumentTypeContext::addType('privacy_policy')
            ::addType('terms_conditions')
            ::addType('cookie_policy');

    # Configurazioni custom (es. aggiunta nuovi doc_type)
        if (file_exists($ROOT."/custom/config/consent.php")) {
            require_once $ROOT."/custom/config/consent.php";
        }
