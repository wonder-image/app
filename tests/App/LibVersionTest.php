<?php

/**
 * Test standalone per il controllo della versione minima di wonder-image/lib
 * usato da `php forge update`.
 *
 *   php tests/App/LibVersionTest.php
 */

declare(strict_types=1);

use Wonder\App\LibVersion;

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

function libRoot(?string $packageJson): string
{
    $root = sys_get_temp_dir() . '/wi-lib-version-' . bin2hex(random_bytes(6));
    mkdir($root, 0777, true);

    if ($packageJson !== null) {
        mkdir($root . '/node_modules/wonder-image', 0777, true);
        file_put_contents($root . '/node_modules/wonder-image/package.json', $packageJson);
    }

    return $root;
}

echo "LibVersion\n";

check('il minimo dichiarato è una versione valida', fn () =>
    preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.]+)?$/', LibVersion::minimum()) === 1);

check('il minimo soddisfa se stesso', fn () => LibVersion::satisfies(LibVersion::minimum()));

check('pre-release più vecchia non soddisfa', fn () =>
    !LibVersion::satisfies('2.1.2-alpha.15', '2.1.2-alpha.23'));

check('le pre-release si confrontano per numero, non per stringa', fn () =>
    !LibVersion::satisfies('2.1.2-alpha.4', '2.1.2-alpha.23')
    && LibVersion::satisfies('2.1.2-alpha.100', '2.1.2-alpha.23'));

check('beta e stabile superano alpha', fn () =>
    LibVersion::satisfies('2.1.2-beta.1', '2.1.2-alpha.23')
    && LibVersion::satisfies('2.1.2', '2.1.2-alpha.23')
    && LibVersion::satisfies('2.2.0-alpha.1', '2.1.2'));

check('la stabile precedente non soddisfa', fn () =>
    !LibVersion::satisfies('2.1.1', '2.1.2-alpha.23'));

check('prefisso v e metadati di build vengono ignorati', fn () =>
    LibVersion::satisfies('v2.1.2-alpha.23+abc123', '2.1.2-alpha.23'));

check('installed() legge node_modules/wonder-image/package.json', fn () =>
    LibVersion::installed(libRoot('{"name":"wonder-image","version":"2.1.2-alpha.15"}')) === '2.1.2-alpha.15');

check('installed() è null senza node_modules', fn () => LibVersion::installed(libRoot(null)) === null);

check('installed() è null con package.json illeggibile o senza versione', fn () =>
    LibVersion::installed(libRoot('{non json')) === null
    && LibVersion::installed(libRoot('{"name":"wonder-image"}')) === null
    && LibVersion::installed(libRoot('{"version":"latest"}')) === null);

check('check() è null quando la lib manca: il controllo non blocca', fn () =>
    LibVersion::check(libRoot(null)) === null);

check('check() è null quando la versione basta', fn () =>
    LibVersion::check(libRoot('{"version":"2.1.2"}'), '2.1.2-alpha.23') === null);

check('check() descrive il problema e il comando da lanciare', function () {
    $result = LibVersion::check(libRoot('{"version":"2.1.2-alpha.15"}'), '2.1.2-alpha.23');

    return $result === [
        'installed' => '2.1.2-alpha.15',
        'minimum' => '2.1.2-alpha.23',
        'command' => "npm install 'wonder-image@^2.1.2-alpha.23'",
    ];
});

summary();
