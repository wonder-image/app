<?php

namespace Wonder\Data\Validators;

use Wonder\Data\Patterns\Pattern;
use Wonder\Data\Patterns\VatPattern;
use Wonder\Data\Support\InputResolver;
use Wonder\Data\Support\ValidationResult;

class VatValidator implements Validator
{
    public function __construct(
        private ?string $countryField = null,
        private ?string $countryIso = null
    ) {
        if ($this->countryField !== null) {
            $this->countryField($this->countryField);
        }

        if ($this->countryIso !== null) {
            $this->countryIso($this->countryIso);
        }
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

    public function validate($value, array $input = []): ValidationResult
    {

        if (!is_scalar($value)) {
            return ValidationResult::error("Partita IVA non valida.", $value);
        }

        $vat = (string) $value;
        $country = null;

        // Priorità: countryIso fisso; fallback: countryField letto dall'input.
        if (is_string($this->countryIso) && trim($this->countryIso) !== '') {
            $country = self::normalizeCountry($this->countryIso);
        } else {
            $resolved = InputResolver::get($this->countryField, $input);
            $country = self::normalizeCountry($resolved);
        }

        // Senza paese non possiamo scegliere il pattern corretto.
        if ($country === null) {
            return ValidationResult::error(
                "Per validare la partita IVA devi impostare countryField() o countryIso().",
                $value
            );
        }

        // La logica di controllo formato è centralizzata in VatValidator::vat.
        $result = self::vat($vat, $country);

        if (!$result->valid) {
            return ValidationResult::error("Partita IVA non valida.", $value);
        }

        return ValidationResult::success($value);
    }

    public static function vat(string $vat, ?string $country = null): object
    {
        $RETURN = (object) [];
        $RETURN->valid = false;
        $RETURN->country = self::normalizeCountry($country);
        $RETURN->number = null;
        $RETURN->vat = null;

        $raw = Pattern::normalizeValue($vat);

        if ($raw === '') {
            return $RETURN;
        }

        if ($RETURN->country !== null) {
            $cc = $RETURN->country;
            $pattern = VatPattern::forCountry($cc);

            if ($pattern === null) {
                return $RETURN;
            }

            $raw = self::stripCountryPrefix($raw, $cc);
            $m = Pattern::match($pattern, $raw);

            if ($m === null) {
                return $RETURN;
            }

            $number = $m[1] ?? $m[0];

            $RETURN->valid = true;
            $RETURN->country = $cc;
            $RETURN->number = $number;
            $RETURN->vat = $cc . $number;

            return $RETURN;
        }

        foreach (VatPattern::allCountries() as $cc => $pattern) {
            $m = Pattern::match($cc . $pattern, $raw);
            if ($m !== null) {
                $RETURN->valid = true;
                $RETURN->country = $cc;
                $RETURN->number = $m[1];
                $RETURN->vat = $cc . $m[1];

                return $RETURN;
            }
        }

        if (preg_match('/^([A-Z]{2})([A-Z0-9]{5,15})$/', $raw, $m)) {
            $cc = $m[1];
            $rx = VatPattern::default();

            $x = ($rx !== null) ? Pattern::match($rx, $m[2]) : null;
            if ($x !== null) {
                $number = $x[1] ?? $x[0];

                $RETURN->valid = true;
                $RETURN->country = $cc;
                $RETURN->number = $number;
                $RETURN->vat = $cc . $number;

                return $RETURN;
            }
        }

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

    private static function stripCountryPrefix(string $value, string $country): string
    {
        return str_starts_with($value, $country)
            ? substr($value, 2)
            : $value;
    }
}
