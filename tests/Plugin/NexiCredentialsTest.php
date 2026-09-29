<?php
/** php tests/Plugin/NexiCredentialsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Plugin\Nexi\Nexi;

check('costruttore API resta retrocompatibile', function () {
    $nexi = new Nexi('api-key', false);

    return $nexi->hasApiCredentials() && !$nexi->hasClassicCredentials();
});

check('alias e chiave MAC sono disponibili', function () {
    $nexi = new Nexi('', false, 'merchant-alias', 'secret');

    return $nexi->alias() === 'merchant-alias'
        && $nexi->macKey() === 'secret'
        && $nexi->hasClassicCredentials();
});

check('MAC avvio XPay classico segue la formula Nexi', function () {
    $nexi = new Nexi('', false, 'merchant-alias', 'secret');

    return $nexi->classicPaymentMac('ORDER123', 'EUR', 1999)
        === '5f0a1bf94296cdc9aa4bac211810cb0b70dd618b';
});

check('MAC esito XPay classico viene verificato', function () {
    $nexi = new Nexi('', false, 'merchant-alias', 'secret');
    $result = [
        'codTrans' => 'ORDER123',
        'esito' => 'OK',
        'importo' => '1999',
        'divisa' => 'EUR',
        'data' => '20260929',
        'orario' => '153000',
        'codAut' => 'ABC123',
    ];

    return $nexi->verifyClassicResultMac($result, 'BD3D522F3929FA4056A5A44D307DD295F05961A7')
        && !$nexi->verifyClassicResultMac($result, str_repeat('0', 40));
});

check('firma classica richiede la chiave MAC', function () {
    try {
        (new Nexi('api-key'))->classicPaymentMac('ORDER123', 'EUR', 1999);
        return false;
    } catch (RuntimeException) {
        return true;
    }
});

summary();
