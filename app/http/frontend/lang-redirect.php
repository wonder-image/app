<?php

    /**
     * Handler convenzionale per la home senza lingua ('/') dei siti in
     * modalità path-prefix. Registrarlo dal consumer con:
     *
     * ```php
     * Route::get('/', $ROOT_APP.'/http/frontend/lang-redirect.php')
     *     ->name('lang-redirect');
     * ```
     */

    (new Wonder\Localization\LanguageRedirector())
        ->redirectByCountry();
