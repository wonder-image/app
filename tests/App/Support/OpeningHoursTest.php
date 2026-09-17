<?php
/** php tests/App/Support/OpeningHoursTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\OpeningHours;

$d = static fn (string $value): DateTimeImmutable => new DateTimeImmutable($value, new DateTimeZone('Europe/Rome'));
$h = static fn (string $openDay, string $open, string $close, string $closeDay = '', string $type = 'regular'): array => [
    'hours_type' => $type,
    'open_day' => $openDay,
    'open_time' => $open,
    'close_day' => $closeDay !== '' ? $closeDay : $openDay,
    'close_time' => $close,
];

$week = [
    $h('Mon', '15:00', '19:00'),
    $h('Mon', '09:00', '13:00'),
    $h('Fri', '22:00', '02:00', 'Sat'),
    $h('Sat', '18:00', '24:00'),
    $h('Mon', '19:00', '22:00', '', 'delivery'),
];
$ferie = [['start_date' => '2026-08-10', 'end_date' => '2026-08-25 00:00:00', 'closed' => 'true', 'open_time' => '', 'close_time' => '']];

check('più fasce nello stesso giorno, ordinate', fn () =>
    OpeningHours::periodsFor($week, [], $d('2026-09-14')) === [
        ['open' => '09:00', 'close' => '13:00', 'overnight' => false],
        ['open' => '15:00', 'close' => '19:00', 'overnight' => false],
    ]
);

check('aperto e chiuso tra le fasce', fn () =>
    OpeningHours::isOpenAt($week, [], $d('2026-09-14 10:00'))
    && !OpeningHours::isOpenAt($week, [], $d('2026-09-14 14:00'))
    && OpeningHours::isOpenAt($week, [], $d('2026-09-14 18:59'))
    && !OpeningHours::isOpenAt($week, [], $d('2026-09-14 19:00'))
);

check('chiusura il giorno dopo', fn () =>
    OpeningHours::isOpenAt($week, [], $d('2026-09-18 23:00'))
    && OpeningHours::isOpenAt($week, [], $d('2026-09-19 01:30'))
    && !OpeningHours::isOpenAt($week, [], $d('2026-09-19 02:00'))
);

check('24:00 chiude a fine giornata', fn () =>
    OpeningHours::isOpenAt($week, [], $d('2026-09-19 23:59'))
    && !OpeningHours::isOpenAt($week, [], $d('2026-09-19 17:59'))
);

check('chiusura vuota = sempre aperto', function () use ($h, $d) {
    $always = [$h('Sun', '00:00', '')];
    $timetable = OpeningHours::timetable($always);

    return OpeningHours::isOpenAt($always, [], $d('2026-09-16 03:00'))
        && count($timetable) === 7
        && $timetable['Wed'] === [['from' => '00:00', 'to' => '24:00']];
});

check('chiusura su un intervallo di date', fn () =>
    OpeningHours::periodsFor($week, $ferie, $d('2026-08-17')) === []
    && !OpeningHours::isOpenAt($week, $ferie, $d('2026-08-17 10:00'))
    && count(OpeningHours::periodsFor($week, $ferie, $d('2026-08-31'))) === 2
);

check('apertura straordinaria in un giorno chiuso', function () use ($week, $d) {
    $special = [['start_date' => '2026-12-20', 'end_date' => '', 'closed' => 'false', 'open_time' => '10:00', 'close_time' => '18:00']];

    return OpeningHours::periodsFor($week, $special, $d('2026-12-20')) === [['open' => '10:00', 'close' => '18:00', 'overnight' => false]]
        && OpeningHours::isOpenAt($week, $special, $d('2026-12-20 11:00'))
        && OpeningHours::periodsFor($week, [], $d('2026-12-20')) === [];
});

check('la chiusura prevale sull\'apertura dello stesso giorno', fn () =>
    OpeningHours::periodsFor($week, [
        ['start_date' => '2026-09-14', 'end_date' => '', 'closed' => 'false', 'open_time' => '10:00', 'close_time' => '12:00'],
        ['start_date' => '2026-09-14', 'end_date' => '', 'closed' => 'true', 'open_time' => '', 'close_time' => ''],
    ], $d('2026-09-14')) === []
);

check('apertura straordinaria oltre la mezzanotte', function () use ($d) {
    $special = [['start_date' => '2026-12-31', 'end_date' => '2027-01-01', 'closed' => 'false', 'open_time' => '20:00', 'close_time' => '03:00']];

    return OpeningHours::isOpenAt([], $special, $d('2026-12-31 23:00'))
        && OpeningHours::isOpenAt([], $special, $d('2027-01-01 02:00'))
        && !OpeningHours::isOpenAt([], $special, $d('2027-01-01 03:00'));
});

check('orari secondari separati dai regolari', fn () =>
    OpeningHours::periodsFor($week, [], $d('2026-09-14'), 'delivery') === [['open' => '19:00', 'close' => '22:00', 'overnight' => false]]
    && !OpeningHours::isOpenAt($week, [], $d('2026-09-14 20:00'))
    && OpeningHours::isOpenAt($week, [], $d('2026-09-14 20:00'), 'delivery')
);

check('le chiusure non toccano gli orari secondari', fn () =>
    OpeningHours::isOpenAt($week, $ferie, $d('2026-08-17 20:00'), 'delivery')
);

check('timetable nel formato di prettyTimeTable', fn () =>
    OpeningHours::timetable($week) === [
        'Mon' => [['from' => '09:00', 'to' => '13:00'], ['from' => '15:00', 'to' => '19:00']],
        'Fri' => [['from' => '22:00', 'to' => '02:00']],
        'Sat' => [['from' => '18:00', 'to' => '24:00']],
    ]
);

check('orari speciali da oggi in avanti, ordinati', function () use ($d) {
    $rows = OpeningHours::upcomingSpecial([
        ['start_date' => '2026-12-24', 'end_date' => '', 'closed' => 'true'],
        ['start_date' => '2026-09-01', 'end_date' => '2026-09-10', 'closed' => 'true'],
        ['start_date' => '2026-09-10', 'end_date' => '2026-09-20 00:00:00', 'closed' => 'true'],
        ['start_date' => '2026-12-20 00:00:00', 'end_date' => null, 'closed' => 'false', 'open_time' => '10:00'],
    ], $d('2026-09-17'));

    return array_column($rows, 'start_date') === ['2026-09-10', '2026-12-20', '2026-12-24']
        && $rows[0]['end_date'] === '2026-09-20'
        && $rows[1]['closed'] === false
        && $rows[2]['closed'] === true;
});

check('orari, date e giorno dopo normalizzati', fn () =>
    OpeningHours::time('9:00') === '09:00'
    && OpeningHours::time('12:00:00') === '12:00'
    && OpeningHours::time('24:00') === '24:00'
    && OpeningHours::time('24:30') === ''
    && OpeningHours::time('abc') === ''
    && OpeningHours::date('2026-09-17 10:00:00') === '2026-09-17'
    && OpeningHours::date('0000-00-00') === ''
    && OpeningHours::nextDay('Sun') === 'Mon'
    && OpeningHours::nextDay('Wed') === 'Thu'
);

summary();
