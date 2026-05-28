<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;

/**
 * Campo password con policy fluent.
 *
 * Le regole vivono in `prepare['password_rules']` (un singolo array assoc).
 * `Resource::prepareFormatFromInput()` le copia in `format['password_rules']`,
 * da dove le legge sia il render — `FormFieldElementFactory::passwordElement()`
 * le propaga al `Wonder\Elements\Form\Components\InputPassword`, che a sua
 * volta lascia che il theme `Wonder` emetta la lista hint sotto l'input — sia
 * `formToArray()`, che le valida server-side via `PasswordPolicyValidator`.
 *
 * Mirror simmetrico di `Wonder\Data\Fields\Password`: se preferisci dichiarare
 * la policy a livello di Model invece che di Resource, la stessa API è
 * disponibile lì.
 */
class InputPassword extends Input
{
    protected string $helper = 'password';

    public function minLength(int $length): static
    {
        return $this->ruleSet('min_length', max(0, $length));
    }

    public function requireUppercase(bool $required = true): static
    {
        return $this->ruleSet('uppercase', $required);
    }

    public function requireLowercase(bool $required = true): static
    {
        return $this->ruleSet('lowercase', $required);
    }

    public function requireNumber(bool $required = true): static
    {
        return $this->ruleSet('number', $required);
    }

    public function requireSpecial(bool $required = true): static
    {
        return $this->ruleSet('special', $required);
    }

    private function ruleSet(string $key, mixed $value): static
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
