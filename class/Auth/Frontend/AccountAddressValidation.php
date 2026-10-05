<?php

namespace Wonder\Auth\Frontend;

use Wonder\App\Schema\Extensions\AddressExtension;

/** Complete-address workflow; deliberately not a Contact model constraint. */
class AccountAddressValidation
{
    public static function schema(AddressExtension $address, array $values = []): AddressExtension
    {
        $required = ['country', 'city', 'cap', 'street', 'number'];
        $country = is_scalar($values['country'] ?? null) ? (string) $values['country']
            : (string) (($address->formSchema()['country'] ?? null)?->get('value') ?? '');
        if (function_exists('states') && states($country) !== []) { $required[] = 'province'; }
        if (($values['type'] ?? 'private') === 'business') {
            $required[] = 'business_name';
        } else {
            $required = [...$required, 'name', 'surname'];
        }
        foreach ($address->dataSchema() as $field) {
            if ($field->isRequired()) { $required[] = $field->key; }
        }
        return $address->requiredFields(array_values(array_unique($required)));
    }

    /** Returns localized messages, never raw values or inline field errors. */
    public static function validate(AddressExtension $address, array $values, array $extraRequiredLabels = []): array
    {
        $labels = $address->labels() + $extraRequiredLabels;
        $errors = [];
        foreach (self::schema($address, $values)->dataSchema() as $field) {
            $key = $field->key;
            $value = $values[$key] ?? null;
            if (!is_scalar($value) && $value !== null) {
                $errors[$key] = 'invalid';
            } elseif ($field->isRequired() && trim((string) $value) === '') {
                $errors[$key] = 'required';
            } elseif (trim((string) $value) !== '' && !$field->validate($value, $values)->isValid()) {
                $errors[$key] = 'invalid';
            }
        }
        foreach ($extraRequiredLabels as $key => $label) {
            if (!is_scalar($values[$key] ?? null) || trim((string) $values[$key]) === '') {
                $errors[$key] = 'required';
            }
        }
        $country = is_string($values['country'] ?? null) ? $values['country'] : '';
        if ($country !== '' && !array_key_exists($country, countries())) { $errors['country'] = 'invalid'; }
        $province = $values['province'] ?? '';
        $states = $country !== '' && !isset($errors['country']) ? states($country) : [];
        if ($states !== [] && is_scalar($province) && $province !== '' && !array_key_exists((string) $province, $states)) {
            $errors['province'] = 'invalid';
        }
        if (array_key_exists('type', $labels) && !in_array($values['type'] ?? '', ['private', 'business'], true)) {
            $errors['type'] = 'invalid';
        }
        return array_map(static fn (string $key): string => (string) \__t(
            'account.validation.'.$errors[$key], ['field' => (string) ($labels[$key] ?? $key)]
        ), array_keys($errors));
    }
}
