<?php

    use Wonder\Localization\{ LanguageContext, TranslationProvider };

    # Imposto le lingue
    LanguageContext::addLangPath($ROOT_APP.'/../resources/lang/')
        ::defaultLang('it')
        ::addLanguage('it', 'Italiano', "https://www.$PAGE->domain/", 'it', ['IT']);

    # Imposto le variabili globali
    TranslationProvider::setGlobals([

            # Dettagli pagina
            'path_site' => LanguageContext::getSitePath(),
            'domain' => $PAGE->domain,  

            # Dettagli società
            'legal_name' => $SOCIETY->legal_name ?? '',
            'legal_address' => empty($SOCIETY->legal_street) ? $SOCIETY->address  ?? '' : $SOCIETY->addressLegal,
            'society_name' => $SOCIETY->name,
            'society_address' => $SOCIETY->address ?? '',
            'society_cf' => $SOCIETY->cf ?? '',
            'society_pi' => $SOCIETY->pi ?? '',
            'society_email' => $SOCIETY->email ?? '',
            'society_tel' => isset($SOCIETY->tel) && $SOCIETY->tel ? prettyPhone($SOCIETY->tel) : '',
            'society_cel' => isset($SOCIETY->cel) && $SOCIETY->cel ? prettyPhone($SOCIETY->cel) : '',
            'society_phone' => !empty($SOCIETY->tel) ? prettyPhone($SOCIETY->tel) : (!empty($SOCIETY->cel) ? prettyPhone($SOCIETY->cel) : ''),

            # Dettagli utente
            'user_name' => $USER->name ?? '',
            'user_surname' => $USER->surname ?? '',
            'user_email' => $USER->email ?? '',
            'user_phone' => $USER->phone ?? '',
            'user_username' => $USER->username ?? '',
            'user_color' => $USER->color ?? ''

        ]);

    if (file_exists($ROOT."/custom/config/lang.php")) {
        require_once $ROOT."/custom/config/lang.php";
    }