<?php
/** php tests/App/Module/ModuleDependencySorterTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Module\Manifest;
use Wonder\App\Module\ModuleDependencySorter;

check('le dipendenze vengono prima', function () {
    return ModuleDependencySorter::sort([
        'ecommerce' => ['gestionale'],
        'gestionale' => [],
    ]) === ['gestionale', 'ecommerce'];
});

check('moduli indipendenti mantengono l\'ordine', function () {
    return ModuleDependencySorter::sort([
        'rsvp' => [],
        'immobili' => [],
        'blog' => [],
    ]) === ['rsvp', 'immobili', 'blog'];
});

check('dipendenze non presenti nell\'elenco ignorate', function () {
    return ModuleDependencySorter::sort(['ecommerce' => ['gestionale']]) === ['ecommerce'];
});

check('catena a tre livelli', function () {
    return ModuleDependencySorter::sort([
        'c' => ['b'],
        'b' => ['a'],
        'a' => [],
    ]) === ['a', 'b', 'c'];
});

check('ciclo: eccezione leggibile', function () {
    try {
        ModuleDependencySorter::sort(['a' => ['b'], 'b' => ['a']]);
        return false;
    } catch (RuntimeException $exception) {
        return str_contains($exception->getMessage(), 'Dipendenze circolari tra i moduli');
    }
});

check('sortManifests ordina i manifest', function () {
    $manifest = fn (string $slug, array $deps) => Manifest::fromArray(
        '/tmp/'.$slug,
        '/tmp/'.$slug.'/module.json',
        ['slug' => $slug, 'dependencies' => ['modules' => $deps]],
        'test'
    );

    $sorted = ModuleDependencySorter::sortManifests([
        'ecommerce' => $manifest('ecommerce', ['gestionale']),
        'gestionale' => $manifest('gestionale', []),
    ]);

    return array_map(fn (Manifest $m) => $m->slug(), $sorted) === ['gestionale', 'ecommerce'];
});

summary();
