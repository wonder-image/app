<?php
/** php tests/App/Diagnostics/MemoryProfilerTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Debug;
use Wonder\App\Diagnostics\MemoryProfiler;

function debugOn(): void {
    $_ENV['APP_DEBUG'] = '1';
    unset($_ENV['MEMORY_PROFILE_ROWS_THRESHOLD'], $_SERVER['MEMORY_PROFILE_ROWS_THRESHOLD']);
    Debug::reset();
    MemoryProfiler::reset();
}

function debugOff(): void {
    $_ENV['APP_DEBUG'] = '0';
    unset($_SERVER['REMOTE_ADDR'], $_SERVER['SERVER_NAME']);
    Debug::reset();
    MemoryProfiler::reset();
}

check('noteQuery ignora sotto soglia righe', function () {
    debugOn();
    MemoryProfiler::noteQuery('App\\Models\\Product', 499); // default soglia 500
    $captured = [];
    MemoryProfiler::setSink(function (string $l) use (&$captured) { $captured[] = $l; });
    MemoryProfiler::report();
    return count($captured) === 1 && str_contains($captured[0], 'heaviest=-');
});

check('noteQuery registra sopra soglia e report nomina il fetch', function () {
    debugOn();
    MemoryProfiler::noteQuery('App\\Models\\Product', 12043);
    $captured = [];
    MemoryProfiler::setSink(function (string $l) use (&$captured) { $captured[] = $l; });
    MemoryProfiler::report();
    return count($captured) === 1
        && str_contains($captured[0], 'App\\Models\\Product::all():12043');
});

check('report sceglie il fetch con piu righe', function () {
    debugOn();
    MemoryProfiler::noteQuery('App\\Models\\A', 800);
    MemoryProfiler::noteQuery('App\\Models\\B', 5000);
    MemoryProfiler::noteQuery('App\\Models\\C', 1200);
    $captured = [];
    MemoryProfiler::setSink(function (string $l) use (&$captured) { $captured[] = $l; });
    MemoryProfiler::report();
    return str_contains($captured[0], 'App\\Models\\B::all():5000');
});

check('soglia righe configurabile via env', function () {
    $_ENV['APP_DEBUG'] = '1';
    $_ENV['MEMORY_PROFILE_ROWS_THRESHOLD'] = '100';
    Debug::reset();
    MemoryProfiler::reset();
    MemoryProfiler::noteQuery('App\\Models\\Small', 150);
    $captured = [];
    MemoryProfiler::setSink(function (string $l) use (&$captured) { $captured[] = $l; });
    MemoryProfiler::report();
    unset($_ENV['MEMORY_PROFILE_ROWS_THRESHOLD']);
    return str_contains($captured[0], 'App\\Models\\Small::all():150');
});

check('report no-op con debug spento', function () {
    debugOff();
    MemoryProfiler::noteQuery('App\\Models\\Product', 99999);
    $captured = [];
    MemoryProfiler::setSink(function (string $l) use (&$captured) { $captured[] = $l; });
    MemoryProfiler::report();
    return $captured === [];
});

check('formatReport livello WARN sopra soglia MB', function () {
    $line = MemoryProfiler::formatReport(200 * 1048576, '/prodotti', null, 128.0);
    return str_contains($line, '[MEM][WARN]')
        && str_contains($line, '/prodotti')
        && str_contains($line, 'peak=200.0MB')
        && str_contains($line, 'heaviest=-');
});

check('formatReport livello INFO sotto soglia MB', function () {
    $line = MemoryProfiler::formatReport(64 * 1048576, '/home', ['model' => 'App\\Models\\X', 'rows' => 700], 128.0);
    return str_contains($line, '[MEM][INFO]')
        && str_contains($line, 'App\\Models\\X::all():700');
});

check('formatReport uri vuoto diventa trattino', function () {
    $line = MemoryProfiler::formatReport(10 * 1048576, '', null, 128.0);
    return str_contains($line, '] - peak=');
});

summary();
