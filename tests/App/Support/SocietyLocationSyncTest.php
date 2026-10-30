<?php
/** php tests/App/Support/SocietyLocationSyncTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Models\Config\SocietyLocation;
use Wonder\App\Support\TableSync;

$GLOBALS['written'] = [];

function sqlSelect(...$args): object
{
    return (object) ['row' => [['id' => 1]]];
}

function sqlInsert(string $table, array $values): void
{
    $GLOBALS['written'][] = $values;
}

function sqlModify(string $table, array $values, string $column, $id): void
{
    $GLOBALS['written'][] = $values;
}

function importLocation(array $row): array
{
    $GLOBALS['written'] = [];
    $import = new ReflectionMethod(TableSync::class, 'importKeepingIds');
    $import->invoke(null, 'society_locations', [$row], SocietyLocation::syncSchema());

    return $GLOBALS['written'][0] ?? [];
}

$fileRow = [
    'id' => 1,
    'label' => 'Sede',
    'business_status' => 'operational',
    'google_place_id' => null,
    'google_sync_hours' => 'false',
    'google_synced_at' => null,
];

check('il deploy aggiorna la sede senza toccare sincronizzazione e stato', function () use ($fileRow) {
    return array_key_exists('label', $written = importLocation($fileRow))
        && !array_intersect(['business_status', 'google_sync_hours', 'google_synced_at'], array_keys($written));
});

check('il Place ID impostato in locale arriva con il deploy', function () use ($fileRow) {
    return (importLocation(['google_place_id' => 'ChIJ-locale'] + $fileRow)['google_place_id'] ?? null) === 'ChIJ-locale';
});

check('un Place ID vuoto nel file non cancella quello esistente', function () use ($fileRow) {
    return !array_key_exists('google_place_id', importLocation($fileRow))
        && !array_key_exists('google_place_id', importLocation(['google_place_id' => ''] + $fileRow));
});

check('una sede nuova da un vecchio file non scrive google_synced_at', function () use ($fileRow) {
    $written = importLocation(['id' => 2] + $fileRow);
    return ($written['label'] ?? null) === 'Sede' && !array_key_exists('google_synced_at', $written);
});

summary();
