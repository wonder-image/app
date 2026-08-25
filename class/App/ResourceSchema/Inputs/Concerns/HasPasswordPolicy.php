<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Setters della password policy.
 *
 * Le regole finiscono in `prepare['password_rules']` (un singolo array
 * assoc): `Resource::prepareFormatFromInput()` le copia in
 * `format['password_rules']`, da dove le legge sia il render (via
 * `FormFieldElementFactory::passwordElement()`, che le propaga all'Element
 * `InputPassword`) sia la validazione server-side dentro `formToArray()`,
 * tramite `PasswordPolicyValidator`.
 *
 * Le stesse API sono mirror di quelle su `Wonder\Data\Fields\Password`, così
 * un Model che dichiara `Field::key('password')->password()->minLength(8)`
 * ottiene la stessa policy senza dover ripassare dal form del Resource.
 */
trait HasPasswordPolicy
{
    use WritesPasswordRules;

    public function minLength(int $length): static
    {
        return $this->passwordRuleSet('min_length', max(0, $length));
    }

    public function requireUppercase(bool $required = true): static
    {
        return $this->passwordRuleSet('uppercase', $required);
    }

    public function requireLowercase(bool $required = true): static
    {
        return $this->passwordRuleSet('lowercase', $required);
    }

    public function requireNumber(bool $required = true): static
    {
        return $this->passwordRuleSet('number', $required);
    }

    public function requireSpecial(bool $required = true): static
    {
        return $this->passwordRuleSet('special', $required);
    }
}
