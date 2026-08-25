<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\reCAPTCHA;
use Wonder\Elements\Form\Field as ElementField;

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

    protected function element(): ElementField
    {
        $context = (array) ($this->schema['context'] ?? []);
        $element = new reCAPTCHA($this->name);

        if (isset($context['recaptcha_action']) && is_string($context['recaptcha_action'])) {
            $element->action($context['recaptcha_action']);
        }

        if (isset($context['recaptcha_theme']) && is_string($context['recaptcha_theme'])) {
            $element->theme($context['recaptcha_theme']);
        }

        if (isset($context['recaptcha_size']) && is_string($context['recaptcha_size'])) {
            $element->size($context['recaptcha_size']);
        }

        return $element;
    }
}
