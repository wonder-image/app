<?php
/** php tests/Backend/Table/ColumnFunctionRegistryTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\Backend\Table\ColumnFunctionRegistry;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

ColumnFunctionRegistry::reset();

// default del framework (usati dalle pagine legacy log email/consent)
eq('mailService allowed', ColumnFunctionRegistry::isAllowed('mailService'), true);
eq('mailLogStatus allowed', ColumnFunctionRegistry::isAllowed('mailLogStatus'), true);
eq('consentEventAction allowed', ColumnFunctionRegistry::isAllowed('consentEventAction'), true);
eq('consentEventSource allowed', ColumnFunctionRegistry::isAllowed('consentEventSource'), true);

// nomi arbitrari dal POST: negati
eq('system denied', ColumnFunctionRegistry::isAllowed('system'), false);
eq('strtoupper denied', ColumnFunctionRegistry::isAllowed('strtoupper'), false);
eq('empty string denied', ColumnFunctionRegistry::isAllowed(''), false);

// estensione esplicita per siti/moduli
ColumnFunctionRegistry::allow('mySiteFn');
eq('allowed after allow()', ColumnFunctionRegistry::isAllowed('mySiteFn'), true);
eq('trimmed lookup', ColumnFunctionRegistry::isAllowed(' mySiteFn '), true);

// reset azzera le estensioni
ColumnFunctionRegistry::reset();
eq('reset clears extra', ColumnFunctionRegistry::isAllowed('mySiteFn'), false);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
