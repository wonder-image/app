<?php

namespace Wonder\Elements\Form\Components;

/**
 * Bottone submit integrato con Google reCAPTCHA v3. Estende `Submit`
 * aggiungendo gli attributi `data-sitekey`, `data-callback`,
 * `data-action` letti dal renderer del tema. La sitekey viene
 * tipicamente recuperata da `\Wonder\App\Credentials::api()`.
 */
class SubmitRecaptcha extends Submit
{
    public function siteKey(string $siteKey): self
    {
        return $this->schema('site_key', $siteKey);
    }

    public function callback(string $callback): self
    {
        return $this->schema('callback', $callback);
    }

    public function action(string $action): self
    {
        return $this->schema('action', $action);
    }
}
