<?php
/** php tests/App/Schema/AddressExtensionPlacesTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\ResourceSchema\Inputs\InputHidden;
use Wonder\App\Schema\Extensions\AddressExtension;

if (!function_exists('__t')) {
    function __t(string $key): string { return $key; }
}

function streetAttribute(AddressExtension $address, string $street = 'street'): string
{
    return (string) $address->formSchema()[$street]->get('attribute');
}

function columnsByName(AddressExtension $address): array
{
    $columns = [];

    foreach ($address->tableSchema() as $column) {
        $columns[$column->name] = $column->getSchema();
    }

    return $columns;
}

function fieldsByKey(AddressExtension $address): array
{
    $fields = [];

    foreach ($address->dataSchema() as $field) {
        $fields[$field->key] = $field;
    }

    return $fields;
}

check('via con data-wi-place vuoto senza prefisso', function () {
    return str_contains(streetAttribute(AddressExtension::simple()->withLink(false)), 'data-wi-place=""');
});

check('via con data-wi-place col prefisso e il trattino basso', function () {
    return str_contains(streetAttribute(AddressExtension::billing('billing'), 'billing_street'), 'data-wi-place="billing_"');
});

check('link acceso: data-wi-place-link col nome del campo', function () {
    return str_contains(streetAttribute(AddressExtension::simple(linkKey: 'gmaps')), 'data-wi-place-link="gmaps"')
        && str_contains(streetAttribute(AddressExtension::simple('legal', 'gmaps'), 'legal_street'), 'data-wi-place-link="legal_gmaps"')
        && !str_contains(streetAttribute(AddressExtension::simple()->withLink(false)), 'data-wi-place-link');
});

check('senza withGeo niente colonne, campi o data-wi-place-id', function () {
    $address = AddressExtension::simple();

    return !array_key_exists('lat', columnsByName($address))
        && !array_key_exists('place_id', $address->formSchema())
        && !str_contains(streetAttribute($address), 'data-wi-place-id');
});

check('withGeo: tre colonne nullable, coordinate decimal(10,7)', function () {
    $columns = columnsByName(AddressExtension::simple('shipping')->withGeo());

    return ($columns['shipping_place_id']['type'] ?? null) === 'VARCHAR'
        && ($columns['shipping_lat']['type'] ?? null) === 'DECIMAL'
        && ($columns['shipping_lat']['length'] ?? null) === '10,7'
        && ($columns['shipping_lng']['length'] ?? null) === '10,7'
        && ($columns['shipping_lat']['null'] ?? false) === true;
});

check('withGeo: tre campi nascosti nel form', function () {
    $schema = AddressExtension::simple('shipping')->withGeo()->formSchema();

    foreach (['shipping_place_id', 'shipping_lat', 'shipping_lng'] as $key) {
        if (!($schema[$key] ?? null) instanceof InputHidden) {
            return false;
        }
    }

    return true;
});

check('withGeo: al salvataggio i valori non validi diventano null', function () {
    $fields = fieldsByKey(AddressExtension::simple()->withGeo());

    return $fields['place_id']->format('ChIJabc_def-123') === 'ChIJabc_def-123'
        && $fields['place_id']->format('<b>no</b>') === null
        && $fields['lat']->format('45.4642035') === 45.4642035
        && $fields['lat']->format('95') === null
        && $fields['lng']->format('-200') === null
        && $fields['lng']->format('') === null;
});

check('withGeo con chiave del place ID: la usa e non la ricrea', function () {
    $address = AddressExtension::simple(linkKey: 'gmaps')->withGeo('google_place_id');
    $columns = columnsByName($address);
    $schema = $address->formSchema();

    return !array_key_exists('place_id', $columns)
        && !array_key_exists('google_place_id', $columns)
        && array_key_exists('lat', $columns)
        && !array_key_exists('place_id', $schema)
        && !array_key_exists('google_place_id', $schema)
        && !array_key_exists('place_id', fieldsByKey($address))
        && str_contains(streetAttribute($address), 'data-wi-place-id="google_place_id"');
});

check('withGeo è immutabile', function () {
    $base = AddressExtension::simple();
    $base->withGeo();

    return !array_key_exists('lat', columnsByName($base));
});

summary();
