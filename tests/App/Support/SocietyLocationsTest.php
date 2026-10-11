<?php
/** php tests/App/Support/SocietyLocationsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\SocietyLocations;

$rows = [
    ['id' => 2, 'slug' => 'brescia', 'label' => 'Brescia', 'is_default' => 'false', 'visible' => 'true', 'position' => 2, 'email' => '', 'tel' => '030 1'],
    ['id' => 1, 'slug' => 'sede-principale', 'label' => 'Sede principale', 'is_default' => 'true', 'visible' => 'true', 'position' => 1, 'email' => 'info@esempio.it', 'tel' => '02 1'],
    ['id' => 3, 'slug' => 'nascosta', 'label' => 'Nascosta', 'is_default' => 'false', 'visible' => 'false', 'position' => 3, 'email' => 'n@esempio.it', 'tel' => ''],
];
$hours = [
    ['society_location_id' => 1, 'hours_type' => 'regular', 'open_day' => 'Mon', 'open_time' => '09:00', 'close_day' => 'Mon', 'close_time' => '18:00', 'position' => 0],
    ['society_location_id' => 3, 'hours_type' => 'regular', 'open_day' => 'Tue', 'open_time' => '10:00', 'close_day' => 'Tue', 'close_time' => '12:00', 'position' => 0],
];
$special = [
    ['society_location_id' => 1, 'start_date' => '2026-12-25', 'end_date' => '', 'closed' => 'true'],
    ['society_location_id' => 3, 'start_date' => '2026-12-26', 'end_date' => '', 'closed' => 'true', 'deleted' => 'true'],
];

check('predefinita dal flag, sedi ordinate per posizione', function () use ($rows, $hours, $special) {
    $data = SocietyLocations::assemble($rows, $hours, $special);

    return $data['default_id'] === 1 && array_keys($data['locations']) === [1, 2, 3];
});

check('senza flag la predefinita è la prima per posizione', function () use ($rows) {
    $rows = array_map(static fn (array $row): array => ['is_default' => 'false'] + $row, $rows);
    $data = SocietyLocations::assemble($rows, [], []);

    return $data['default_id'] === 1
        && $data['locations'][1]->is_default === 'true'
        && $data['locations'][2]->is_default === 'false';
});

check('contatti e orari con le chiusure presi dalla predefinita', function () use ($rows, $hours, $special) {
    $brescia = SocietyLocations::assemble($rows, $hours, $special)['locations'][2];

    return $brescia->email === 'info@esempio.it' && $brescia->tel === '030 1'
        && $brescia->hoursInherited === true
        && count($brescia->hours) === 1 && $brescia->hours[0]['open_day'] === 'Mon'
        && count($brescia->specialHours) === 1;
});

check('orari propri e righe cancellate ignorate', function () use ($rows, $hours, $special) {
    $nascosta = SocietyLocations::assemble($rows, $hours, $special)['locations'][3];

    return $nascosta->hoursInherited === false
        && $nascosta->hours[0]['open_day'] === 'Tue'
        && $nascosta->specialHours === [];
});

check('nessuna sede', fn () => SocietyLocations::assemble([], [], []) === ['default_id' => 0, 'locations' => []]);

// Al boot delle rotte backend le funzioni sql non sono ancora caricate: quel vuoto non deve restare.
check('prima delle funzioni sql il vuoto non resta in cache', function () {
    if (function_exists('sqlTableExists')) {
        return false;
    }

    SocietyLocations::reset();
    $prima = SocietyLocations::all(false);
    $GLOBALS['tabelleChieste'] = [];

    function sqlTableExists($table)
    {
        $GLOBALS['tabelleChieste'][] = $table;

        return false;
    }

    SocietyLocations::all(false);

    return $prima === [] && $GLOBALS['tabelleChieste'] !== [];
});

check('copia di Google e recensioni solo col place_id della sede', function () use ($rows, $hours, $special) {
    $rows[0]['google_place_id'] = 'ChIJ2';
    $rows[1]['google_place_id'] = 'ChIJ1';
    $google = [
        ['society_location_id' => 1, 'place_id' => 'ChIJ1', 'rating' => '4.5', 'rating_count' => '8'],
        ['society_location_id' => 2, 'place_id' => 'ChIJvecchio', 'rating' => '3'],
    ];
    $reviews = [
        ['society_location_id' => 1, 'name' => 'r1', 'rating' => '5', 'text' => 'Ok &#128512;', 'publish_time' => '2026-10-01 10:00:00'],
        ['society_location_id' => 2, 'name' => 'r2', 'rating' => '1'],
    ];
    $locations = SocietyLocations::assemble($rows, $hours, $special, $google, $reviews)['locations'];

    return $locations[1]->google['rating'] === 4.5
        && $locations[1]->reviews[0]['text'] === 'Ok 😀'
        && $locations[2]->google === null && $locations[2]->reviews === []
        && $locations[3]->google === null;
});

summary();
