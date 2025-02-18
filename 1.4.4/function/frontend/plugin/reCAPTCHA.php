<?php

    function verifyRecaptcha($POST) {

        global $ALERT;

        if ($POST['g-recaptcha-response'] === $POST['g-recaptcha-token']) {
            $reCAPTCHA = new Wonder\Plugin\Google\Security\reCAPTCHA();
            $ALERT = $reCAPTCHA->verify($POST['g-recaptcha-token'], $POST['g-recaptcha-action'])->valid ? null : 617;
        } else {
            $ALERT = 617;
        }

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