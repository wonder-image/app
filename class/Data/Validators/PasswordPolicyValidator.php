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
                $this->translate('forms.password_errors.min_length', ['count' => (string) $minLength]),
                $value
            );
        }

        if (!empty($this->rules['uppercase']) && !preg_match('/[A-Z]/', $string)) {
            return ValidationResult::error(
                $this->translate('forms.password_errors.uppercase'),
                $value
            );
        }

        if (!empty($this->rules['lowercase']) && !preg_match('/[a-z]/', $string)) {
            return ValidationResult::error(
                $this->translate('forms.password_errors.lowercase'),
                $value
            );
        }

        if (!empty($this->rules['number']) && !preg_match('/\d/', $string)) {
            return ValidationResult::error(
                $this->translate('forms.password_errors.number'),
                $value
            );
        }

        if (!empty($this->rules['special']) && !preg_match('/[^A-Za-z0-9]/', $string)) {
            return ValidationResult::error(
                $this->translate('forms.password_errors.special'),
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

    /**
     * `__t()` lancia eccezione su chiave mancante; qui la catturiamo per
     * evitare di bloccare un submit solo perché il sito non ha aggiornato
     * i `components.json` con le chiavi `forms.password_errors.*`.
     *
     * @param array<string, string> $replacements
     */
    private function translate(string $key, array $replacements = []): string
    {
        if (function_exists('__t')) {
            try {
                return (string) __t($key, $replacements);
            } catch (\Throwable) {
                // fall through to fallback below
            }
        }

        $fallback = (string) (end(($segments = explode('.', $key))) ?: $key);
        $fallback = ucfirst(str_replace('_', ' ', $fallback));

        foreach ($replacements as $name => $value) {
            $fallback = str_replace('{{'.$name.'}}', (string) $value, $fallback);
        }

        return $fallback;
    }
}
