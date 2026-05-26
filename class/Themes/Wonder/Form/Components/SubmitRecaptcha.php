<?php

namespace Wonder\Themes\Wonder\Form\Components;

/**
 * Renderer Wonder di `SubmitRecaptcha`: come il Submit, ma type="button"
 * obbligatorio (la submission è triggerata dal callback di reCAPTCHA)
 * e gli attributi `data-sitekey/data-callback/data-action` per
 * l'integrazione con il widget `g-recaptcha`.
 */
class SubmitRecaptcha extends Submit
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $label = $this->escape((string) ($this->schema['label'] ?? 'Invia'));
        $buttonClass = $this->escape(trim((string) ($this->schema['button_class'] ?? 'btn btn-success').' wi-submit g-recaptcha'));
        $siteKey = $this->escape((string) ($this->schema['site_key'] ?? ''));
        $callback = $this->escape((string) ($this->schema['callback'] ?? 'sendForm'));
        $action = $this->escape((string) ($this->schema['action'] ?? 'submit'));

        return "<button type=\"button\" id=\"{$id}\" class=\"{$buttonClass}\" name=\"{$name}\" data-sitekey=\"{$siteKey}\" data-callback=\"{$callback}\" data-action=\"{$action}\" disabled>{$label}</button>";
    }
}
