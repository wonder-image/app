<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;

/**
 * Google reCAPTCHA v2 — "Casella di controllo: Non sono un robot".
 *
 * Rende il widget `g-recaptcha` con sitekey letta da `Credentials::api()` e
 * gli input hidden richiesti dalla verifica server-side `verifyRecaptcha`.
 */
class InputReCaptcha extends Input
{
    protected string $helper = 'recaptcha';

    /** `submit` (default), oppure una action logica validata server-side. */
    public function action(string $action): static
    {
        $action = trim($action);

        return $action !== '' ? $this->context('recaptcha_action', $action) : $this;
    }

    /** `light` (default) o `dark`. */
    public function theme(string $theme): static
    {
        $theme = trim($theme);

        return $theme !== '' ? $this->context('recaptcha_theme', $theme) : $this;
    }

    /** `normal` (default) o `compact`. */
    public function size(string $size): static
    {
        $size = trim($size);

        return $size !== '' ? $this->context('recaptcha_size', $size) : $this;
    }
}
