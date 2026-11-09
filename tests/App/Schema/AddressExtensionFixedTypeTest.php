<?php
/** php tests/App/Schema/AddressExtensionFixedTypeTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Schema\Extensions\AddressExtension;
use Wonder\Auth\Frontend\AccountAddressValidation;

if (!function_exists('__t')) {
    function __t(string $key): string { return $key; }
}

function columnNames(AddressExtension $address): array
{
    return array_map(static fn ($column) => $column->name, $address->tableSchema());
}

function requiredKeys(AddressExtension $address, array $values = []): array
{
    $keys = [];
    foreach (AccountAddressValidation::schema($address, $values)->dataSchema() as $field) {
        if ($field->isRequired()) {
            $keys[] = $field->key;
        }
    }

    return $keys;
}

check('billing invariato: tipo, nome e dati da società nello stesso ordine', function () {
    $names = columnNames(AddressExtension::billing());
    $expected = ['name', 'surname', 'type', 'business_name', 'cf', 'pi', 'sdi', 'pec'];

    return array_values(array_intersect($names, $expected)) === $expected
        && AddressExtension::billing()->fixedType() === null;
});

check('asPrivate: nome, cognome e CF, niente tipo né dati da società', function () {
    $address = AddressExtension::billing()->asPrivate();
    $names = columnNames($address);

    return $address->fixedType() === 'private'
        && array_intersect(['name', 'surname', 'cf'], $names) === ['name', 'surname', 'cf']
        && array_intersect(['type', 'business_name', 'pi', 'sdi', 'pec'], $names) === []
        && !array_key_exists('type', $address->formSchema());
});

check('asBusiness: dati da società e CF, niente tipo né nome', function () {
    $address = AddressExtension::billing('billing')->asBusiness();
    $names = columnNames($address);

    return $address->fixedType() === 'business'
        && count(array_intersect(['billing_business_name', 'billing_cf', 'billing_pi', 'billing_sdi', 'billing_pec'], $names)) === 5
        && array_intersect(['billing_type', 'billing_name', 'billing_surname'], $names) === [];
});

check('withCompanyData(false) lascia il codice fiscale', function () {
    $names = columnNames(AddressExtension::billing()->withCompanyData(false));

    return in_array('cf', $names, true) && !in_array('pi', $names, true) && !in_array('business_name', $names, true);
});

check('validazione asBusiness: ragione sociale obbligatoria anche senza campo tipo', function () {
    $required = requiredKeys(AddressExtension::billing()->asBusiness());

    return in_array('business_name', $required, true) && !in_array('name', $required, true);
});

check('validazione asPrivate: nome e cognome obbligatori', function () {
    $required = requiredKeys(AddressExtension::billing()->asPrivate(), ['type' => 'business']);

    return in_array('name', $required, true) && in_array('surname', $required, true)
        && !in_array('business_name', $required, true);
});

check('decorate inserisce il tipo fisso', function () {
    $row = AddressExtension::billing()->asBusiness()->decorate(['business_name' => 'Acme']);

    return ($row['type'] ?? null) === 'business';
});

summary();
