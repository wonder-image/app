<?php

namespace Wonder\Data\Fields;

use Wonder\Data\Formatters\String\TrimFormatter;
use Wonder\Data\Validators\PasswordPolicyValidator;
use Wonder\Data\Validators\StringValidator;

class Password extends Field
{
    public string $type = 'password';

    public function __construct(string $key)
    {
        parent::__construct($key);

        $this->validators([
            new StringValidator(),
        ]);

        $this->formatters([
            new TrimFormatter(),
        ]);
    }

    public function minLength(int $minLength): static
    {
        return $this->ruleSet('min_length', max(0, $minLength));
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

    public function defaultInputFormat(): array
    {
        $format = [];
        $rules = (array) $this->getSchema('password_rules');

        if ($rules !== []) {
            $format['password_rules'] = $rules;
        }

        return $format;
    }

    private function ruleSet(string $key, mixed $value): static
    {
        $rules = (array) $this->getSchema('password_rules');

        if ($value === false || $value === 0 || $value === '0') {
            unset($rules[$key]);
        } else {
            $rules[$key] = $value;
        }

        $this->schema('password_rules', $rules);

        $this->syncPolicyValidator($rules);

        return $this;
    }

    /**
     * Mantiene esattamente un `PasswordPolicyValidator` nell'array dei
     * validators, allineato alle regole correnti. Se l'array è vuoto, lo
     * rimuove. Evita duplicati quando i setters sono chiamati in catena.
     *
     * @param array<string, mixed> $rules
     */
    private function syncPolicyValidator(array $rules): void
    {
        $validators = (array) $this->getSchema('validators');

        $filtered = array_values(array_filter(
            $validators,
            static fn($v) => !($v instanceof PasswordPolicyValidator)
        ));

        if ($rules !== []) {
            $filtered[] = new PasswordPolicyValidator($rules);
        }

        $this->schema('validators', $filtered);
    }
}
