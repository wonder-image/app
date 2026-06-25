<?php

namespace Wonder\Elements\Form\Components;

use Wonder\Elements\Form\Components\InputText;

class InputPassword extends InputText
{
    public string $type = 'password';

    /**
     * Le regole di policy sono memorizzate in `schema['password_rules']`.
     * Il renderer Wonder le usa per emettere la lista hint sotto l'input
     * (`<ul class="wi-password-rules">`) con `data-wi-rule="..."`. Il client
     * `wonder-image/lib` agganciandosi a `data-wi-password-rules` switcha
     * le icone `bi-x` ↔ `bi-check` live mentre l'utente digita.
     *
     * Stesso array (`min_length`, `uppercase`, `lowercase`, `number`,
     * `special`) viene replicato da `FormField->password()` in
     * `prepare['password_rules']` per la validazione server-side eseguita
     * da `formToArray()` via `PasswordPolicyValidator`.
     */
    public function minLength($minlength): self
    {
        parent::minLength($minlength);

        return $this->ruleSet('min_length', max(0, (int) $minlength));
    }

    public function requireUppercase(bool $required = true): self
    {
        return $this->ruleSet('uppercase', $required);
    }

    public function requireLowercase(bool $required = true): self
    {
        return $this->ruleSet('lowercase', $required);
    }

    public function requireNumber(bool $required = true): self
    {
        return $this->ruleSet('number', $required);
    }

    public function requireSpecial(bool $required = true): self
    {
        return $this->ruleSet('special', $required);
    }

    private function ruleSet(string $key, mixed $value): self
    {
        $rules = (array) ($this->schema['password_rules'] ?? []);

        if ($value === false || $value === 0 || $value === '0') {
            unset($rules[$key]);
        } else {
            $rules[$key] = $value;
        }

        return $this->schema('password_rules', $rules);
    }
}
