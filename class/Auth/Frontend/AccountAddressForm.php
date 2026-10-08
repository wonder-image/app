<?php

namespace Wonder\Auth\Frontend;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\Schema\Extensions\AddressExtension;
use Wonder\Elements\Components\Container;

/** Shared address presentation, independent of account persistence and routes. */
class AccountAddressForm
{
    public static function fields(AddressExtension $address, array $values = [], bool $submitted = false): array
    {
        $country = isset($values['country']) && is_scalar($values['country']) ? (string) $values['country'] : null;
        $fields = AccountAddressValidation::schema($address, $values)->formSchema($country !== '' ? $country : null);
        $labels = $address->labels();
        if (isset($fields['province'])) {
            $fields['province']->context('required_when_states_available', true);
        }

        foreach ($fields as $key => $field) {
            if (isset($fields['type']) && in_array($key, ['business_name', 'name', 'surname'], true)) {
                $field->required();
            }
            $field->label((string) ($labels[$key] ?? ''));
            if (array_key_exists($key, $values) && (is_scalar($values[$key]) || $values[$key] === null)) {
                $value = (string) ($values[$key] ?? '');
                if ($submitted || $value !== '') {
                    $field->value($value);
                }
            }
        }

        if (isset($fields['phone_prefix'])) {
            $prefix = trim((string) ($fields['phone_prefix']->get('value') ?? ''));
            if (!$submitted && $prefix === '' && function_exists('countryPhonePrefix')) {
                $prefix = (string) countryPhonePrefix($fields['country']->get('value') ?? '');
            }
            if ($prefix !== '' && !str_starts_with($prefix, '+')) {
                $prefix = '+'.ltrim($prefix, '0');
            }
            $fields['phone_prefix']->value($prefix);
        }

        return $fields;
    }

    public static function layout(array $fields): Container
    {
        $components = [];
        $keys = array_unique([
            'label', 'type', 'business_name', 'name', 'surname', 'birth_date', 'current_email', 'email', 'current_password', 'password', 'cf', 'pi', 'sdi', 'pec',
            'country', 'province', 'city', 'cap', 'street', 'number', 'more', 'phone_prefix', 'phone',
            ...array_keys($fields),
        ]);
        foreach ($keys as $key) {
            if (!array_key_exists($key, $fields)) { continue; }
            $field = $fields[$key];
            if (!$field instanceof Input) {
                throw new \InvalidArgumentException('Address fields must use FormField inputs');
            }
            $desktop = match ($key) {
                'label', 'type', 'business_name', 'cf', 'pec', 'more', 'email', 'current_email', 'current_password', 'password' => 12,
                'city', 'street' => 9,
                'cap', 'number', 'phone_prefix' => 3,
                'phone' => 9,
                default => 6,
            };
            $phone = match ($key) { 'phone_prefix' => 1, 'phone' => 3, default => 4 };
            // One grid item only: nested floated wrappers shrink the input and
            // leave empty grid cells when a conditional field is hidden.
            $container = (new Container())->noGrid()
                ->class('f-none col-'.$desktop.($phone !== $desktop ? ' col-p-'.$phone : ''))
                ->style('display', 'flex')
                ->style('min-width', '0')
                ->components([$field]);
            if (isset($fields['type']) && in_array($key, ['business_name', 'pi', 'sdi', 'pec'], true)) {
                $container->visibleWhen('type', 'business');
                $field->visibleWhen('type', 'business');
            }
            if (isset($fields['type']) && in_array($key, ['name', 'surname'], true)) {
                $container->visibleWhen('type', 'private');
                $field->visibleWhen('type', 'private');
            }
            $components[] = $container;
        }

        return (new Container())->columns(['default' => 4, 'md' => 12])->gap(4)->components($components);
    }
}
