<?php

namespace Wonder\Data\Validators;

use Wonder\Data\Patterns\Pattern;
use Wonder\Data\Patterns\TinPattern;
use Wonder\Data\Support\InputResolver;
use Wonder\Data\Support\ValidationResult;

class TinValidator implements Validator
{
    public function __construct(
        private ?string $countryField = null,
        private ?string $countryIso = null,
        private string $type = 'private'
    ) {
        if ($this->countryField !== null) {
            $this->countryField($this->countryField);
        }

        if ($this->countryIso !== null) {
            $this->countryIso($this->countryIso);
        }

        $this->type($this->type);
    }

    public function countryField(string $field): self
    {
        $field = trim($field);

        if ($field === '') {
            throw new \InvalidArgumentException("countryField non può essere vuoto.");
        }

        $this->countryField = $field;
        $this->countryIso = null;

        return $this;
    }

    public function countryIso(string $iso2): self
    {
        $iso2 = self::normalizeCountry($iso2);

        if ($iso2 === null) {
            throw new \InvalidArgumentException("countryIso non può essere vuoto.");
        }

        $this->countryIso = $iso2;
        $this->countryField = null;

        return $this;
    }

    public function type(string $type = 'private'): self
    {
        $type = strtolower(trim($type));

        if (!in_array($type, ['private', 'business', 'all'], true)) {
            throw new \InvalidArgumentException("type deve essere private, business o all.");
        }

        $this->type = $type;

        return $this;
    }

    public function validate($value, array $input = []): ValidationResult
    {
        
        if (!is_scalar($value)) {
            return ValidationResult::error("TIN non valido.", $value);
        }

        $tin = (string) $value;
        $country = null;

        // Priorità: countryIso fisso; fallback: countryField letto dall'input.
        if (is_string($this->countryIso) && trim($this->countryIso) !== '') {
            $country = self::normalizeCountry($this->countryIso);
        } else {
            $resolved = InputResolver::get($this->countryField, $input);
            $country = self::normalizeCountry($resolved);
        }

        // Senza paese non possiamo validare il TIN.
        if ($country === null || trim($country) === '') {
            return ValidationResult::error(
                "Per validare il TIN devi impostare countryField() o countryIso().",
                $value
            );
        }

        // La logica di controllo formato è centralizzata in TinValidator::tin.
        $result = self::tin($tin, $country, $this->type);

        if (!$result->valid) {
            return ValidationResult::error("TIN non valido.", $value);
        }

        return ValidationResult::success($value);
    }

    public static function tin(string $tin, string $country, ?string $type = null): object
    {
        $RETURN = (object) [];
        $RETURN->valid = false;
        $RETURN->tin = null;
        $RETURN->country = self::normalizeCountry($country) ?? '';

        $raw = Pattern::normalizeValue($tin);
        $type = self::normalizeType($type, 'all');

        if ($raw === '' || $RETURN->country === '') {
            return $RETURN;
        }

        $cc = $RETURN->country;
        $pattern = TinPattern::resolve($cc, $type);

        if ($pattern === null) {
            return $RETURN;
        }

        $raw = self::stripCountryPrefix($raw, $cc);
        $m = Pattern::match($pattern, $raw);

        if ($m === null) {
            return $RETURN;
        }

        $RETURN->valid = true;
        $RETURN->country = $cc;
        $RETURN->tin = $m[0];

        return $RETURN;
    }

    private static function normalizeCountry(?string $country): ?string
    {
        if ($country === null) {
            return null;
        }

        $country = strtoupper(trim($country));

        return $country !== '' ? $country : null;
    }

    private static function normalizeType(?string $type, string $default = 'all'): string
    {
        $type = strtolower(trim((string) $type));

        return in_array($type, ['business', 'private', 'all'], true)
            ? $type
            : $default;
    }

    private static function stripCountryPrefix(string $value, string $country): string
    {
        return str_starts_with($value, $country)
            ? substr($value, 2)
            : $value;
    }
}
