<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

use Wonder\Elements\Form\Components\InputPassword as PasswordElement;

/**
 * Setters della password policy.
 *
 * Le regole finiscono in `prepare['password_rules']` (un singolo array
 * assoc): `Resource::prepareFormatFromInput()` le copia in
 * `format['password_rules']`, da dove le legge sia il render — via
 * {@see applyPasswordPolicy()}, che le propaga all'Element `InputPassword`,
 * il quale a sua volta lascia che il theme `Wonder` emetta la lista hint
 * sotto l'input — sia la validazione server-side dentro `formToArray()`,
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

    /** Copia la policy raccolta in `prepare['password_rules']` sull'Element. */
    protected function applyPasswordPolicy(PasswordElement $element): PasswordElement
    {
        $rules = (array) ($this->schema['prepare']['password_rules'] ?? []);

        if (isset($rules['min_length']) && (int) $rules['min_length'] > 0) {
            $element->minLength((int) $rules['min_length']);
        }

        if (!empty($rules['uppercase'])) {
            $element->requireUppercase();
        }

        if (!empty($rules['lowercase'])) {
            $element->requireLowercase();
        }

        if (!empty($rules['number'])) {
            $element->requireNumber();
        }

        if (!empty($rules['special'])) {
            $element->requireSpecial();
        }

        return $element;
    }
}
