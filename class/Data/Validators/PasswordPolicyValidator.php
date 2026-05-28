<?php

namespace Wonder\Data\Validators;

use Wonder\Data\Support\ValidationResult;

/**
 * Verifica una password contro un set di regole simmetriche a quelle
 * mostrate dal renderer Wonder sotto il campo input (vedi
 * `Wonder\Themes\Wonder\Form\Components\InputPassword`).
 *
 * Le regole si registrano via i setters fluent su:
 *   - `Wonder\Elements\Form\Components\InputPassword` (lato render),
 *   - `Wonder\Data\Fields\Password` (lato Model/Validator).
 *
 * Il bridge col mondo `FormField`/`Resource` passa per
 * `prepare['password_rules']` che `Resource::prepareFormatFromInput()`
 * copia in `format['password_rules']`. `formToArray()` istanzia poi
 * questo validator quando vede la chiave.
 *
 * Chiavi attese: min_length (int), uppercase, lowercase, number, special.
 */
class PasswordPolicyValidator implements Validator
{
    /**
     * @param array<string, mixed> $rules
     */
    public function __construct(private array $rules = [])
    {
    }

    public function validate($value, array $input = []): ValidationResult
    {
        $string = is_string($value) ? $value : '';

        // Se il valore è vuoto, lasciamo decidere al `RequiredValidator`.
        // Una password vuota su un campo opzionale non deve far esplodere
        // la policy.
        if ($string === '') {
            return ValidationResult::success($value);
        }

        $minLength = isset($this->rules['min_length']) ? (int) $this->rules['min_length'] : 0;

        if ($minLength > 0 && mb_strlen($string) < $minLength) {
            return ValidationResult::error(
                $this->translate('La password deve contenere almeno %d caratteri.', $minLength),
                $value
            );
        }

        if (!empty($this->rules['uppercase']) && !preg_match('/[A-Z]/', $string)) {
            return ValidationResult::error(
                $this->translate('La password deve contenere almeno una lettera maiuscola.'),
                $value
            );
        }

        if (!empty($this->rules['lowercase']) && !preg_match('/[a-z]/', $string)) {
            return ValidationResult::error(
                $this->translate('La password deve contenere almeno una lettera minuscola.'),
                $value
            );
        }

        if (!empty($this->rules['number']) && !preg_match('/\d/', $string)) {
            return ValidationResult::error(
                $this->translate('La password deve contenere almeno un numero.'),
                $value
            );
        }

        if (!empty($this->rules['special']) && !preg_match('/[^A-Za-z0-9]/', $string)) {
            return ValidationResult::error(
                $this->translate('La password deve contenere almeno un carattere speciale.'),
                $value
            );
        }

        return ValidationResult::success($value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    private function translate(string $key, int|string|null $arg = null): string
    {
        if (function_exists('__t')) {
            $translated = (string) __t($key);

            if ($arg !== null && str_contains($translated, '%d')) {
                return sprintf($translated, (int) $arg);
            }

            if ($arg !== null && str_contains($translated, '%s')) {
                return sprintf($translated, (string) $arg);
            }

            return $translated;
        }

        if ($arg !== null && str_contains($key, '%d')) {
            return sprintf($key, (int) $arg);
        }

        if ($arg !== null && str_contains($key, '%s')) {
            return sprintf($key, (string) $arg);
        }

        return $key;
    }
}
