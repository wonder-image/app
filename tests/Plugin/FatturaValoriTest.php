<?php
/** php tests/Plugin/FatturaValoriTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Plugin\Custom\Fattura\Valori\AliquoteIva;
use Wonder\Plugin\Custom\Fattura\Valori\EsigibilitaIva;
use Wonder\Plugin\Custom\Fattura\Valori\Natura;

check('aliquote italiane con due decimali', function () {
    return array_keys(AliquoteIva::Valori) === ['22.00', '10.00', '5.00', '4.00']
        && AliquoteIva::Valori['22.00'] === 'Aliquota ordinaria'
        && AliquoteIva::Valori['4.00'] === 'Aliquota minima';
});

check('esigibilità I, D, S', function () {
    return array_keys(EsigibilitaIva::Valori) === ['I', 'D', 'S'];
});

check('nature valide contenute in Valori, senza N2, N3 e N6', function () {
    foreach (Natura::VALIDE as $code) {
        if (!array_key_exists($code, Natura::Valori)) {
            return false;
        }
    }
    return !in_array('N2', Natura::VALIDE, true)
        && !in_array('N3', Natura::VALIDE, true)
        && !in_array('N6', Natura::VALIDE, true)
        && count(Natura::VALIDE) === 21;
});

check('valide() restituisce codice e descrizione nell\'ordine di Valori', function () {
    $valide = Natura::valide();
    return array_keys($valide) === Natura::VALIDE && $valide['N2.2'] === Natura::Valori['N2.2'];
});

check('Valori di Natura invariati (compatibilità)', function () {
    return array_key_exists('N2', Natura::Valori) && array_key_exists('N6', Natura::Valori);
});

summary();
