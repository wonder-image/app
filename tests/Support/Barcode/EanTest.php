<?php
/** php tests/Support/Barcode/EanTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Support\Barcode\Ean;

check('EAN-13 calcola la cifra di controllo', function () {
    $ean = Ean::encode('400638133393', 13);

    return $ean['code'] === '4006381333931'
        && strlen($ean['modules']) === 95
        && str_starts_with($ean['modules'], '101')
        && str_ends_with($ean['modules'], '101');
});

check('EAN-8 calcola la cifra di controllo', function () {
    $ean = Ean::encode('9638507', 8);

    return $ean['code'] === '96385074'
        && strlen($ean['modules']) === 67;
});

check('checksum già presente viene validato', function () {
    return Ean::encode('4006381333931', 13)['code'] === '4006381333931'
        && Ean::encode('96385074', 8)['code'] === '96385074';
});

check('checksum errato viene rifiutato', function () {
    try {
        Ean::encode('4006381333932', 13);
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
});

check('pattern convertito in alternanza di barre e spazi', function () {
    $ean = Ean::encode('96385074', 8);
    $runs = Ean::modulesToRuns($ean['modules']);

    return array_sum(array_map('intval', str_split($runs))) === 67;
});

summary();
