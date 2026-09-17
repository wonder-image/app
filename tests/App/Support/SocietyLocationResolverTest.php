<?php
/** php tests/App/Support/SocietyLocationResolverTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\GoogleMapsLink;
use Wonder\App\Support\SocietyLocationDefaults;
use Wonder\App\Support\SocietyLocationResolver;

$default = [
    'id' => 1, 'is_default' => 'true',
    'email' => 'info@esempio.it', 'pec' => 'pec@esempio.it', 'tel' => '02 123', 'cel' => '',
    'site' => 'https://esempio.it', 'instagram' => 'https://instagram.com/esempio',
    'name' => 'Esempio', 'legal_name' => 'Esempio srl', 'pi' => '01234567890', 'cf' => '01234567890',
    'sdi' => 'ABC1234', 'rea' => 'MI-1', 'share_capital' => '10000.00',
    'country' => 'IT', 'province' => 'MI', 'city' => 'Milano', 'cap' => '20100', 'street' => 'Via Roma',
    'number' => '1', 'more' => '', 'gmaps' => '', 'google_place_id' => 'ChIJ-milano', 'google_synced_at' => null,
    'legal_country' => 'IT', 'legal_province' => 'MI', 'legal_city' => 'Milano', 'legal_cap' => '20100',
    'legal_street' => 'Via Legale', 'legal_number' => '2', 'legal_more' => '', 'legal_gmaps' => '',
];
$branch = [
    'id' => 2, 'is_default' => 'false',
    'email' => '', 'pec' => null, 'tel' => '030 999', 'cel' => '',
    'site' => '', 'instagram' => 'https://instagram.com/negozio',
    'name' => '', 'legal_name' => '', 'pi' => '', 'cf' => '', 'sdi' => '', 'rea' => '', 'share_capital' => null,
    'country' => 'IT', 'province' => 'BS', 'city' => 'Brescia', 'cap' => '25100', 'street' => '',
    'number' => '', 'more' => '', 'gmaps' => '', 'google_place_id' => '', 'google_synced_at' => null,
    'legal_country' => 'IT', 'legal_province' => '', 'legal_city' => '', 'legal_cap' => '',
    'legal_street' => '', 'legal_number' => '', 'legal_more' => '', 'legal_gmaps' => '',
];

check('contatti e link campo per campo', function () use ($default, $branch) {
    $r = SocietyLocationResolver::resolve($branch, $default);

    return $r['tel'] === '030 999' && $r['email'] === 'info@esempio.it' && $r['pec'] === 'pec@esempio.it'
        && $r['cel'] === '' && $r['instagram'] === 'https://instagram.com/negozio' && $r['site'] === 'https://esempio.it';
});

check('dati legali e sede legale vuoti: ereditati per intero', function () use ($default, $branch) {
    $r = SocietyLocationResolver::resolve($branch, $default);

    return $r['legal_name'] === 'Esempio srl' && $r['share_capital'] === '10000.00'
        && $r['legal_street'] === 'Via Legale' && $r['legal_city'] === 'Milano';
});

check('indirizzo compilato in parte: nessun campo ereditato', function () use ($default, $branch) {
    $r = SocietyLocationResolver::resolve($branch, $default);

    return $r['city'] === 'Brescia' && $r['street'] === '' && $r['google_place_id'] === ''
        && !in_array('street', $r['inherited_fields'], true);
});

check('il solo paese non rende compilato un gruppo', function () use ($default, $branch) {
    $branch['city'] = '';
    $branch['province'] = '';
    $branch['cap'] = '';
    $r = SocietyLocationResolver::resolve($branch, $default);

    return $r['street'] === 'Via Roma' && $r['google_place_id'] === 'ChIJ-milano';
});

check('dati legali compilati in parte restano della sede', function () use ($default, $branch) {
    $branch['legal_name'] = 'Negozio Brescia srl';
    $r = SocietyLocationResolver::resolve($branch, $default);

    return $r['legal_name'] === 'Negozio Brescia srl' && $r['pi'] === '' && $r['name'] === 'Esempio';
});

check('nome dell\'attività sempre della predefinita, anche se la sede lo ha', function () use ($default, $branch) {
    $branch['name'] = 'Altro nome';
    $branch['legal_name'] = 'Franchising srl';
    $r = SocietyLocationResolver::resolve($branch, $default);

    return $r['name'] === 'Esempio' && in_array('name', $r['inherited_fields'], true)
        && SocietyLocationResolver::resolve($default, $default)['name'] === 'Esempio';
});

check('la predefinita non eredita da sé stessa', fn () =>
    SocietyLocationResolver::resolve($default, $default)['inherited_fields'] === []
    && SocietyLocationResolver::resolve($branch, null)['email'] === ''
);

check('valori ereditati per i suggerimenti del form', function () use ($default, $branch) {
    $values = SocietyLocationResolver::inheritedValues($branch, $default);

    return ($values['email'] ?? null) === 'info@esempio.it' && ($values['legal_name'] ?? null) === 'Esempio srl'
        && ($values['name'] ?? null) === 'Esempio'
        && !array_key_exists('tel', $values) && !array_key_exists('street', $values) && !array_key_exists('cel', $values);
});

check('nuova sede: suggerimenti da tutti i gruppi della predefinita', function () use ($default) {
    $values = SocietyLocationResolver::inheritedValues([], $default);

    return ($values['street'] ?? null) === 'Via Roma' && ($values['pi'] ?? null) === '01234567890'
        && ($values['site'] ?? null) === 'https://esempio.it';
});

check('orari propri o della predefinita, con le chiusure', function () {
    $own = [['open_day' => 'Mon']];
    $ownSpecial = [['start_date' => '2026-08-10']];
    $def = [['open_day' => 'Tue']];
    $defSpecial = [['start_date' => '2026-12-25']];

    return SocietyLocationResolver::hours($own, [], $def, $defSpecial, false) === ['hours' => $own, 'special_hours' => [], 'inherited' => false]
        && SocietyLocationResolver::hours([], $ownSpecial, $def, $defSpecial, false) === ['hours' => $def, 'special_hours' => $defSpecial, 'inherited' => true]
        && SocietyLocationResolver::hours([], $ownSpecial, $def, $defSpecial, true) === ['hours' => [], 'special_hours' => $ownSpecial, 'inherited' => false];
});

check('link a Google Maps dal Place ID', fn () =>
    GoogleMapsLink::forPlace('ChIJ-milano', 'Via Roma 1, Milano') === 'https://www.google.com/maps/search/?api=1&query=Via%20Roma%201%2C%20Milano&query_place_id=ChIJ-milano'
    && GoogleMapsLink::forPlace('ChIJ-x') === 'https://www.google.com/maps/search/?api=1&query=ChIJ-x&query_place_id=ChIJ-x'
    && GoogleMapsLink::forPlace('  ') === ''
);

check('una sola predefinita, mai eliminabile', fn () =>
    SocietyLocationDefaults::flagOnSave('false', false) === 'true'
    && SocietyLocationDefaults::flagOnSave('true', true) === 'true'
    && SocietyLocationDefaults::flagOnSave('false', true) === 'false'
    && SocietyLocationDefaults::flagOnSave(null, true) === 'false'
    && !SocietyLocationDefaults::canDelete(['is_default' => 'true'])
    && SocietyLocationDefaults::canDelete(['is_default' => 'false'])
);

summary();
