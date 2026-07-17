<?php
/** php tests/Backend/Table/ColumnFormatterRegistryTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\Backend\Table\ColumnFormatterRegistry;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

ColumnFormatterRegistry::reset();

eq('unknown not registered', ColumnFormatterRegistry::has('immobili.prezzo'), false);
eq('call unknown returns empty', ColumnFormatterRegistry::call('immobili.prezzo', ['prezzo' => 10]), '');

ColumnFormatterRegistry::register('immobili.prezzo', static fn (array $row): string => '€ ' . (int) ($row['prezzo'] ?? 0));
eq('registered', ColumnFormatterRegistry::has('immobili.prezzo'), true);
eq('call runs with row', ColumnFormatterRegistry::call('immobili.prezzo', ['prezzo' => 255000]), '€ 255000');
eq('trimmed lookup', ColumnFormatterRegistry::has(' immobili.prezzo '), true);

// il valore di ritorno è castato a stringa
ColumnFormatterRegistry::register('n', static fn (array $row) => 42);
eq('return cast to string', ColumnFormatterRegistry::call('n', []), '42');

ColumnFormatterRegistry::reset();
eq('reset clears', ColumnFormatterRegistry::has('immobili.prezzo'), false);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
