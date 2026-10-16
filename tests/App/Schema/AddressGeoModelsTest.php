<?php
/** php tests/App/Schema/AddressGeoModelsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';
require_once __DIR__ . '/../../../app/function/string/date.php';
require_once __DIR__ . '/../../../app/function/other/geo.php';

use Wonder\App\Models\Config\SocietyLocation;
use Wonder\App\ResourceSchema\Inputs\InputHidden;
use Wonder\App\Schema\Extensions\AddressExtension;
use Wonder\App\Resources\Config\SocietyLocationResource;
use Wonder\Auth\Frontend\AccountAddressValidation;
use Wonder\Auth\Frontend\AccountAddressForm;

if (!function_exists('__t')) {
    function __t(string $key): string { return $key; }
}

/** Lo stesso indirizzo delle schede cliente di customer-account, senza il modulo. */
function accountAddress(): AddressExtension
{
    return AddressExtension::simple(countryDefault: 'IT')->withLink(false)->withContactName()->withPhone()->withGeo();
}

/** Nomi degli input messi nel layout, a qualunque profondità. */
function layoutInputNames(object $node): array
{
    $names = property_exists($node, 'name') && is_string($node->name) ? [$node->name] : [];

    foreach ((property_exists($node, 'components') ? $node->components : []) as $child) {
        if (is_object($child)) {
            $names = [...$names, ...layoutInputNames($child)];
        }
    }

    return $names;
}

check('sedi: coordinate per la sede, place ID e coordinate per la sede legale', function () {
    $columns = array_keys(SocietyLocation::getColumns());

    return array_diff(['lat', 'lng', 'legal_place_id', 'legal_lat', 'legal_lng', 'google_place_id'], $columns) === []
        && !in_array('place_id', $columns, true);
});

check('sedi: la via principale scrive il place ID in google_place_id', function () {
    $street = SocietyLocationResource::formFieldsByKey()['street'] ?? null;

    return $street !== null
        && str_contains((string) $street->get('attribute'), 'data-wi-place-id="google_place_id"')
        && str_contains((string) $street->get('attribute'), 'data-wi-place-link="gmaps"');
});

check('sedi: il campo google_place_id resta visibile', function () {
    $field = SocietyLocationResource::formFieldsByKey()['google_place_id'] ?? null;

    return $field !== null && !$field instanceof InputHidden;
});

check('sedi: i campi nascosti sono nel layout', function () {
    $names = layoutInputNames(SocietyLocationResource::formLayoutSchema());

    return array_diff(['lat', 'lng', 'legal_place_id', 'legal_lat', 'legal_lng'], $names) === [];
});

check('form dell\'account: campi nascosti con i valori salvati', function () {
    $fields = AccountAddressForm::fields(accountAddress(), ['place_id' => 'ChIJabc_def-123', 'lat' => '45.1', 'lng' => '9.2']);

    return ($fields['place_id'] ?? null) instanceof InputHidden
        && $fields['place_id']->get('value') === 'ChIJabc_def-123'
        && ($fields['lat'] ?? null) instanceof InputHidden
        && str_contains((string) $fields['street']->get('attribute'), 'data-wi-place=""');
});

check('form dell\'account: i campi nascosti non prendono una cella della griglia', function () {
    $layout = AccountAddressForm::layout(AccountAddressForm::fields(accountAddress()));
    $direct = array_filter($layout->components, static fn ($item) => $item instanceof InputHidden);

    return count($direct) === 3;
});

check('form dell\'account: da telefono l\'indirizzo da solo, poi civico e provincia, città e CAP, poi altro', function () {
    $fields = AccountAddressForm::fields(accountAddress());
    $cells = [];
    foreach (AccountAddressForm::layout($fields)->components as $item) {
        if ($item instanceof InputHidden) { continue; }
        $key = array_search($item->components[0], $fields, true);
        $cells[$key] = implode(' ', (array) $item->getAttr('class'));
    }
    $order = array_values(array_intersect(array_keys($cells), ['country', 'street', 'number', 'more', 'province', 'city', 'cap']));

    return $order === ['country', 'street', 'number', 'province', 'city', 'cap', 'more']
        && str_contains($cells['street'], 'col-9 col-p-5') && str_contains($cells['number'], 'col-3 col-p-2')
        && str_contains($cells['province'], 'col-4 col-p-3') && str_contains($cells['city'], 'col-5 col-p-3')
        && str_contains($cells['cap'], 'col-3 col-p-2')
        && str_contains(implode(' ', (array) AccountAddressForm::layout($fields)->getAttr('class')), 'gap-p-3');
});

summary();
