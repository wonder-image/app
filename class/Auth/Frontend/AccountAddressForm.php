<?php

namespace Wonder\Auth\Frontend;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\InputHidden;
use Wonder\App\Schema\Extensions\AddressExtension;
use Wonder\Elements\Components\Container;

/** Shared address presentation, independent of account persistence and routes. */
class AccountAddressForm
{
    public static function fields(AddressExtension $address, array $values = [], bool $submitted = false): array
    {
        $key = $address->fieldKey(...);
        $country = isset($values[$key('country')]) && is_scalar($values[$key('country')]) ? (string) $values[$key('country')] : null;
        $fields = AccountAddressValidation::schema($address, $values)->formSchema($country !== '' ? $country : null);
        $labels = $address->labels();
        if (isset($fields[$key('province')])) {
            $fields[$key('province')]->context('required_when_states_available', true);
        }
        $named = array_map($key, ['business_name', 'name', 'surname']);

        foreach ($fields as $name => $field) {
            if (isset($fields[$key('type')]) && in_array($name, $named, true)) {
                $field->required();
            }
            $field->label((string) ($labels[$name] ?? ''));
            if (array_key_exists($name, $values) && (is_scalar($values[$name]) || $values[$name] === null)) {
                $value = (string) ($values[$name] ?? '');
                if ($submitted || $value !== '') {
                    $field->value($value);
                }
            }
        }

        if (isset($fields[$key('phone_prefix')])) {
            $prefix = trim((string) ($fields[$key('phone_prefix')]->get('value') ?? ''));
            if (!$submitted && $prefix === '' && function_exists('countryPhonePrefix')) {
                $prefix = (string) countryPhonePrefix($fields[$key('country')]->get('value') ?? '');
            }
            if ($prefix !== '' && !str_starts_with($prefix, '+')) {
                $prefix = '+'.ltrim($prefix, '0');
            }
            $fields[$key('phone_prefix')]->value($prefix);
        }

        return $fields;
    }

    /** `$address` serve solo se i campi hanno un prefisso (`shipping_street`): ordine e larghezze seguono il nome senza. */
    public static function layout(array $fields, ?AddressExtension $address = null): Container
    {
        $components = [];
        $prefix = $address !== null && $address->prefix() !== '' ? $address->prefix().'_' : '';
        $base = static fn (string $name): string => $prefix !== '' && str_starts_with($name, $prefix) ? substr($name, strlen($prefix)) : $name;
        $named = [];
        foreach (array_keys($fields) as $name) {
            $named[$base((string) $name)] ??= (string) $name;
        }
        $type = $named['type'] ?? null;
        $keys = array_unique([
            'label', 'type', 'business_name', 'name', 'surname', 'birth_date', 'current_email', 'email', 'current_password', 'password', 'cf', 'pi', 'sdi', 'pec',
            'country', 'street', 'number', 'province', 'city', 'cap', 'more', 'phone_prefix', 'phone',
            ...array_keys($named),
        ]);
        foreach ($keys as $key) {
            if (!isset($named[$key])) { continue; }
            $field = $fields[$named[$key]];
            if (!$field instanceof Input) {
                throw new \InvalidArgumentException('Address fields must use FormField inputs');
            }
            if ($field instanceof InputHidden) {
                $components[] = $field;
                continue;
            }
            $desktop = match ($key) {
                'label', 'type', 'business_name', 'cf', 'pec', 'country', 'more', 'email', 'current_email', 'current_password', 'password' => 12,
                'street', 'phone' => 9,
                'number', 'cap', 'phone_prefix' => 3,
                'province' => 4,
                'city' => 5,
                default => 6,
            };
            // Da telefono, su 5 colonne: l'indirizzo da solo, poi civico e provincia, città e CAP, prefisso e telefono.
            $phone = match ($key) { 'number', 'cap', 'phone_prefix' => 2, 'province', 'city', 'phone' => 3, default => 5 };
            // One grid item only: nested floated wrappers shrink the input and
            // leave empty grid cells when a conditional field is hidden.
            $container = (new Container())->noGrid()
                ->class('f-none col-'.$desktop.($phone !== $desktop ? ' col-p-'.$phone : ''))
                ->style('display', 'flex')
                ->style('min-width', '0')
                ->components([$field]);
            if ($type !== null && in_array($key, ['business_name', 'pi', 'sdi', 'pec'], true)) {
                $container->visibleWhen($type, 'business');
                $field->visibleWhen($type, 'business');
            }
            if ($type !== null && in_array($key, ['name', 'surname'], true)) {
                $container->visibleWhen($type, 'private');
                $field->visibleWhen($type, 'private');
            }
            $components[] = $container;
        }

        return (new Container())->columns(['default' => 5, 'md' => 12])->gap(4)->class('w-100 gap-p-3')->components($components);
    }
}
