<?php
/** php tests/App/Support/OpeningHoursInputTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\OpeningHoursInput;

check('orari: stesso giorno, mezzanotte, giorno dopo, righe vuote saltate', function () {
    $result = OpeningHoursInput::hours([
        ['id' => '7', 'hours_type' => 'regular', 'open_day' => 'Mon', 'open_time' => '09:00', 'close_day' => '', 'close_time' => '13:00'],
        ['hours_type' => '', 'open_day' => 'Sat', 'open_time' => '18:00', 'close_day' => '', 'close_time' => '00:00'],
        ['hours_type' => 'delivery', 'open_day' => 'Fri', 'open_time' => '22:00', 'close_day' => 'Sat', 'close_time' => '02:00'],
        ['hours_type' => 'regular', 'open_day' => 'Sun', 'open_time' => '', 'close_day' => '', 'close_time' => ''],
    ]);

    return $result['errors'] === [] && $result['rows'] === [
        ['id' => '7', 'hours_type' => 'regular', 'open_day' => 'Mon', 'open_time' => '09:00', 'close_day' => 'Mon', 'close_time' => '13:00'],
        ['hours_type' => 'regular', 'open_day' => 'Sat', 'open_time' => '18:00', 'close_day' => 'Sat', 'close_time' => '24:00'],
        ['hours_type' => 'delivery', 'open_day' => 'Fri', 'open_time' => '22:00', 'close_day' => 'Sat', 'close_time' => '02:00'],
    ];
});

check('sempre aperto: chiusura vuota', function () {
    $result = OpeningHoursInput::hours([
        ['hours_type' => 'regular', 'open_day' => 'Sun', 'open_time' => '00:00', 'close_day' => 'Mon', 'close_time' => ''],
    ]);

    return $result['errors'] === [] && $result['rows'][0]['close_day'] === '' && $result['rows'][0]['close_time'] === '';
});

check('errori sugli orari, con il numero di riga', function () {
    $result = OpeningHoursInput::hours([
        ['hours_type' => 'regular', 'open_day' => 'Mon', 'open_time' => '13:00', 'close_day' => '', 'close_time' => '09:00'],
        ['hours_type' => 'regular', 'open_day' => 'Mon', 'open_time' => '09:00', 'close_day' => 'Wed', 'close_time' => '02:00'],
        ['hours_type' => 'sconosciuto', 'open_day' => 'Mon', 'open_time' => '09:00', 'close_day' => '', 'close_time' => '10:00'],
    ]);

    return $result['rows'] === [] && count($result['errors']) === 3 && str_contains($result['errors'][0], 'riga 1');
});

check('chiusure e aperture straordinarie', function () {
    $result = OpeningHoursInput::specialHours([
        ['id' => '3', 'start_date' => '10/08/2026', 'end_date' => '25/08/2026', 'closed' => 'true', 'open_time' => '', 'close_time' => '', 'note' => 'Ferie'],
        ['start_date' => '2026-12-20', 'end_date' => '', 'closed' => 'false', 'open_time' => '10:00', 'close_time' => '18:00', 'note' => ''],
        ['start_date' => '31/12/2026', 'end_date' => '', 'closed' => 'false', 'open_time' => '20:00', 'close_time' => '03:00', 'note' => ''],
        ['start_date' => '', 'end_date' => '', 'closed' => 'true', 'open_time' => '', 'close_time' => '', 'note' => ''],
    ]);

    return $result['errors'] === [] && $result['rows'] === [
        ['id' => '3', 'start_date' => '2026-08-10', 'end_date' => '2026-08-25', 'closed' => 'true', 'open_time' => '', 'close_time' => '', 'note' => 'Ferie', 'source' => 'manual'],
        ['start_date' => '2026-12-20', 'end_date' => '', 'closed' => 'false', 'open_time' => '10:00', 'close_time' => '18:00', 'note' => '', 'source' => 'manual'],
        ['start_date' => '2026-12-31', 'end_date' => '2027-01-01', 'closed' => 'false', 'open_time' => '20:00', 'close_time' => '03:00', 'note' => '', 'source' => 'manual'],
    ];
});

check('errori su chiusure e aperture', function () {
    $result = OpeningHoursInput::specialHours([
        ['start_date' => '25/08/2026', 'end_date' => '10/08/2026', 'closed' => 'true'],
        ['start_date' => '20/12/2026', 'end_date' => '', 'closed' => 'false', 'open_time' => '', 'close_time' => ''],
        ['start_date' => '20/12/2026', 'end_date' => '23/12/2026', 'closed' => 'false', 'open_time' => '10:00', 'close_time' => '12:00'],
        ['start_date' => '32/13/2026', 'closed' => 'true'],
    ]);

    return $result['rows'] === [] && count($result['errors']) === 4;
});

check('nel form 24:00 diventa 00:00, che al salvataggio torna 24:00', function () {
    $form = OpeningHoursInput::forForm([
        ['id' => '11', 'hours_type' => 'regular', 'open_day' => 'Mon', 'open_time' => '11:00', 'close_day' => 'Mon', 'close_time' => '24:00'],
        ['id' => '12', 'hours_type' => 'regular', 'open_day' => 'Fri', 'open_time' => '22:00', 'close_day' => 'Sat', 'close_time' => '02:00'],
    ]);
    $saved = OpeningHoursInput::hours($form);

    return $form[0]['close_time'] === '00:00'
        && $form[1]['close_time'] === '02:00'
        && $saved['errors'] === []
        && $saved['rows'][0]['close_time'] === '24:00'
        && $saved['rows'][0]['close_day'] === 'Mon';
});

summary();
