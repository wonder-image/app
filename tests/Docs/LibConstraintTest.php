<?php
/**
 * Il vincolo npm di package.json ripete il minimo di extra.wonder.lib in
 * composer.json: la versione minima della lib resta dichiarata in un punto
 * solo e il package.json non può restare indietro.
 *
 *   php tests/Docs/LibConstraintTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\LibVersion;

echo "LibConstraint\n";

$root = dirname(__DIR__, 2);

check('package.json dichiara wonder-image con lo stesso vincolo di composer.json', function () use ($root) {
    $composer = json_decode((string) file_get_contents($root.'/composer.json'), true);
    $package = json_decode((string) file_get_contents($root.'/package.json'), true);

    $expected = (string) ($composer['extra']['wonder']['lib'] ?? '');
    $actual = (string) ($package['dependencies'][LibVersion::PACKAGE] ?? '');

    if ($expected !== $actual) {
        echo "    composer.json: {$expected} / package.json: {$actual}\n";
    }

    return $expected !== '' && $expected === $actual;
});

check('package.json è privato e ha lo script docs', function () use ($root) {
    $package = json_decode((string) file_get_contents($root.'/package.json'), true);

    return ($package['private'] ?? false) === true && ($package['scripts']['docs'] ?? '') === 'php bin/docs.php';
});

check('composer.json espone lo script docs', function () use ($root) {
    $composer = json_decode((string) file_get_contents($root.'/composer.json'), true);

    return ($composer['scripts']['docs'] ?? '') === '@php bin/docs.php';
});

summary();
