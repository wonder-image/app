<?php
/** php tests/App/Support/LegacySocietyMapperTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\LegacySocietyMapper;

check('sede predefinita dalle vecchie tabelle, solo colonne esistenti', fn () =>
    LegacySocietyMapper::location(
        ['id' => 1, 'name' => 'Esempio', 'email' => 'info@esempio.it', 'deleted' => 'false', 'creation' => '2025-01-01 10:00:00', 'custom_column' => 'x'],
        ['id' => 1, 'street' => 'Via Roma', 'city' => 'Milano', 'timetable' => '{}'],
        ['id' => 1, 'legal_street' => 'Via Legale'],
        ['id' => 1, 'site' => 'https://esempio.it', 'instagram' => ''],
        ['slug', 'label', 'is_default', 'visible', 'position', 'business_status', 'name', 'email', 'street', 'city', 'legal_street', 'instagram', 'site']
    ) === [
        'slug' => 'sede-principale',
        'label' => 'Sede principale',
        'is_default' => 'true',
        'visible' => 'true',
        'position' => 1,
        'business_status' => 'operational',
        'name' => 'Esempio',
        'email' => 'info@esempio.it',
        'street' => 'Via Roma',
        'city' => 'Milano',
        'legal_street' => 'Via Legale',
        'site' => 'https://esempio.it',
        'instagram' => '',
    ]
);

check('orari da society_timetable, righe cancellate escluse, JSON ignorato', fn () =>
    LegacySocietyMapper::hours([
        ['day' => 'Mon', 'from_time' => '09:00', 'to_time' => '13:00', 'deleted' => 'false'],
        ['day' => 'Mon', 'from_time' => '15:00', 'to_time' => '19:00', 'deleted' => 'false'],
        ['day' => 'Tue', 'from_time' => '09:00', 'to_time' => '13:00', 'deleted' => 'true'],
    ], '{"Wed":[{"from":"08:00","to":"12:00"}]}') === [
        ['hours_type' => 'regular', 'open_day' => 'Mon', 'open_time' => '09:00', 'close_day' => 'Mon', 'close_time' => '13:00', 'position' => 0],
        ['hours_type' => 'regular', 'open_day' => 'Mon', 'open_time' => '15:00', 'close_day' => 'Mon', 'close_time' => '19:00', 'position' => 1],
    ]
);

check('orari dal JSON se la tabella è vuota; mezzanotte e giorno dopo', fn () =>
    LegacySocietyMapper::hours([], '{"Wed":[{"from":"8:00","to":"12:00"}],"Fri":[{"from":"20:00","to":"00:00"},{"from":"22:00","to":"02:00"}]}') === [
        ['hours_type' => 'regular', 'open_day' => 'Wed', 'open_time' => '08:00', 'close_day' => 'Wed', 'close_time' => '12:00', 'position' => 0],
        ['hours_type' => 'regular', 'open_day' => 'Fri', 'open_time' => '20:00', 'close_day' => 'Fri', 'close_time' => '24:00', 'position' => 1],
        ['hours_type' => 'regular', 'open_day' => 'Fri', 'open_time' => '22:00', 'close_day' => 'Sat', 'close_time' => '02:00', 'position' => 2],
    ]
);

check('righe non valide e JSON non valido ignorati', fn () =>
    LegacySocietyMapper::hours([
        ['day' => 'Xyz', 'from_time' => '09:00', 'to_time' => '10:00'],
        ['day' => 'Mon', 'from_time' => '', 'to_time' => '10:00'],
    ], null) === []
    && LegacySocietyMapper::hours([], 'non json') === []
);

summary();
