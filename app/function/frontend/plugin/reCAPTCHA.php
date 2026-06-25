<?php

    /**
     * Helper procedurali per reCAPTCHA v3.
     *
     * - `inputRecaptcha()` è una compatibility shim sopra
     *   `Wonder\Plugin\Custom\Input\reCAPTCHA`. Nuovo codice: usa
     *   direttamente la classe.
     * - `verifyRecaptcha()` resta funzione utility server-side legacy.
     */

    /**
     * Verifica server-side legacy basata su `$GLOBALS['ALERT']`.
     *
     * @deprecated Nuovo codice: usa `Wonder\App\Security\RecaptchaGuard`
     *             (o `static::verifyRecaptcha()` nei Resource), che centralizza
     *             validazione, logging ed eccezione 617 senza toccare `$ALERT`.
     *             Questa funzione resta solo per backward compatibility e ora
     *             delega al guard preservando firma, return `bool` e `$ALERT`.
     */
    function verifyRecaptcha($POST) {

        global $ALERT;

        // Match v2 storico (`g-recaptcha-response` == token) preservato per BC.
        if (($POST['g-recaptcha-response'] ?? null) !== ($POST['g-recaptcha-token'] ?? null)) {
            $ALERT = 617;
            return false;
        }

        $passes = Wonder\App\Security\RecaptchaGuard::for((string) ($POST['g-recaptcha-action'] ?? ''))
            ->withRequest($POST)
            ->passes();

        $ALERT = $passes ? null : 617;

        return $passes;

    }

    /**
     * Summary of inputRecaptcha
     * @param mixed $action
     * @param mixed $theme = light || dark
     * @param mixed $size = compact || normal
     * @return void
     */
    function inputRecaptcha($action = null, $theme = null, $size = null) {
        
        return (new Wonder\Plugin\Custom\Input\reCAPTCHA())
                ->action($action)
                ->theme($theme)
                ->size($size)
                ->generate();
                
    }