<?php

    use Wonder\Localization\{ LanguageContext, TranslationProvider };

    $SOCIETY ??= (object) [];

    # Imposto le lingue
        LanguageContext::addLangPath($ROOT_APP.'/../resources/lang/')
            ::defaultLang('it')
            ::addLanguage('it', 'Italiano', "https://www.$PAGE->domain/", 'it', ['IT']);
    
    # Inizializzo il sistema di traduzione
        TranslationProvider::init();

        
    # Informazioni della società
        try {

            if (sqlTableExists('society')) {

                $SOCIETY = infoSociety();

            } else {

                $SOCIETY->name = "Wonder Image";
                $SOCIETY->legal_name = "Wonder Image";
                $SOCIETY->email = "info@wonderimage.it";

            }

        } catch (\Throwable) {

            $SOCIETY->name = "Wonder Image";
            $SOCIETY->legal_name = "Wonder Image";
            $SOCIETY->email = "info@wonderimage.it";

        }

        foreach ([
            'prettyAddress' => '--',
            'prettyLegal' => '',
            'gmaps' => '',
            'tel' => '',
            'cel' => '',
            'social' => [],
            'logo' => '',
            'logoWhite' => '',
            'logoBlack' => '',
            'icon' => '',
            'favicon' => '',
            'appIcon' => '',
            'address' => '',
            'addressLegal' => '',
            'cf' => '',
            'pi' => '',
        ] as $field => $value) {
            if (!isset($SOCIETY->$field)) {
                $SOCIETY->$field = $value;
            }
        }
        
    # Modifico impostazioni logo
        $PATH->logo = isset($SOCIETY->logo) ? __i($SOCIETY->logo)->size(480)->url() : '';
        $PATH->logoWhite = isset($SOCIETY->logoWhite) ? __i($SOCIETY->logoWhite)->size(480)->url() : '';
        $PATH->logoBlack = isset($SOCIETY->logoBlack) ? __i($SOCIETY->logoBlack)->size(480)->url() : '';
        $PATH->logoIcon = isset($SOCIETY->logoIcon) ? __i($SOCIETY->icon)->size(480)->url() : '';
        $PATH->favicon = $SOCIETY->favicon ?? '';
        $PATH->appIcon = isset($SOCIETY->appIcon) ? __i($SOCIETY->appIcon)->size(480)->url() : '';

    # Imposto le variabili globali
        TranslationProvider::setGlobals([

            # Dettagli pagina
            'path_site' => LanguageContext::getSitePath(),
            'path_privacy_policy' => __u('legal/privacy-policy'),
            'path_cookie_policy' => __u('legal/cookie-policy'),
            'path_terms_conditions' => __u('legal/terms-conditions'),
            'domain' => $PAGE->domain,

            # Dettagli utente
            'user_name' => $USER->name ?? '',
            'user_surname' => $USER->surname ?? '',
            'user_email' => $USER->email ?? '',
            'user_phone' => $USER->phone ?? '',
            'user_username' => $USER->username ?? '',
            'user_color' => $USER->color ?? '',

            # Dettagli società
            'legal_name' => $SOCIETY->legal_name ?? '',
            'legal_address' => empty($SOCIETY->legal_street) ? $SOCIETY->address  ?? '' : $SOCIETY->addressLegal,
            'society_name' => $SOCIETY->name ?? '',
            'society_address' => $SOCIETY->address ?? '',
            'society_cf' => $SOCIETY->cf ?? '',
            'society_pi' => $SOCIETY->pi ?? '',
            'society_email' => $SOCIETY->email ?? '',
            'society_tel' => isset($SOCIETY->tel) && $SOCIETY->tel ? prettyPhone($SOCIETY->tel) : '',
            'society_cel' => isset($SOCIETY->cel) && $SOCIETY->cel ? prettyPhone($SOCIETY->cel) : '',
            'society_phone' => !empty($SOCIETY->tel) ? prettyPhone($SOCIETY->tel) : (!empty($SOCIETY->cel) ? prettyPhone($SOCIETY->cel) : '')

        ]);
        

    if (file_exists($ROOT."/custom/config/lang.php")) {
        require_once $ROOT."/custom/config/lang.php";
    }
