<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\App\Credentials;
use Wonder\Themes\Wonder\Form\Field;

/**
 * Renderer Wonder dell'Element `reCAPTCHA` (v2 checkbox "Non sono un
 * robot").
 *
 * Output:
 *  - `<div class="g-recaptcha" data-wi-…>` (widget Google)
 *  - `<input type="hidden" name="g-recaptcha-token" required>`
 *    (riempito dal JS frontend al success del check)
 *  - `<input type="hidden" name="g-recaptcha-action" required>`
 *    (riempito dal JS frontend; il valore atteso è verificato
 *    server-side da `verifyRecaptcha`)
 *
 * `siteKey` è letta da `\Wonder\App\Credentials::api()->g_recaptcha_site_key`
 * — è una credenziale globale del sito, non un parametro per chiamata.
 */
class reCAPTCHA extends Field
{
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderInput();
    }

    public function renderInput(): string
    {
        $siteKey = $this->escape((string) (Credentials::api()->g_recaptcha_site_key ?? ''));
        $theme = $this->escape((string) ($this->schema['recaptcha_theme'] ?? 'light'));
        $size = $this->escape((string) ($this->schema['recaptcha_size'] ?? 'normal'));
        $action = $this->escape((string) ($this->schema['recaptcha_action'] ?? 'submit'));

        return
            "<div class=\"g-recaptcha\" data-wi-site-key=\"{$siteKey}\" data-wi-theme=\"{$theme}\" data-wi-size=\"{$size}\" data-wi-action=\"{$action}\"></div>"
            .'<input type="hidden" name="g-recaptcha-token" required>'
            .'<input type="hidden" name="g-recaptcha-action" required>';
    }
}
