<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Plumbing condiviso fra `HasPasswordPolicy` (API pubblica di `InputPassword`)
 * e gli shim `@deprecated` rimasti su `FormField`. Tiene in un unico posto la
 * scrittura di `prepare['password_rules']`.
 *
 * Passare `false`/`0` a una regola la *rimuove* dall'array invece di
 * registrarla a falso, così `requireUppercase(false)` equivale a non averla
 * mai richiesta — che è ciò che sia il render sia `PasswordPolicyValidator`
 * si aspettano.
 */
trait WritesPasswordRules
{
    protected function passwordRuleSet(string $key, mixed $value): static
    {
        $rules = (array) (($this->schema['prepare']['password_rules'] ?? []) ?: []);

        if ($value === false || $value === 0 || $value === '0') {
            unset($rules[$key]);
        } else {
            $rules[$key] = $value;
        }

        return $this->prepare('password_rules', $rules);
    }
}
