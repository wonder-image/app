<?php
/** php tests/App/ResourceSchema/TableColumnFormatterClosureTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\App\ResourceSchema\TableColumn;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    if ($got !== $expected) { $fail++; echo "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  got: " . var_export($got, true) . "\n"; }
    else { echo "ok: $label\n"; }
}

// stringa: invariato
$s = TableColumn::key('prezzo')->formatter('immobili.prezzo')->toArray();
eq('string stored', $s['formatter'] ?? null, 'immobili.prezzo');

// closure: memorizzata così com'è
$fn = static fn (array $row): string => 'X';
$c = TableColumn::key('prezzo')->formatter($fn)->toArray();
eq('closure stored', ($c['formatter'] ?? null) instanceof Closure, true);
eq('closure is same', $c['formatter'] === $fn, true);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
