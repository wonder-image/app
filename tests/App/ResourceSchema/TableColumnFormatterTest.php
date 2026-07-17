<?php
/** php tests/App/ResourceSchema/TableColumnFormatterTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\App\ResourceSchema\TableColumn;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

$schema = TableColumn::key('nome')->formatter('immobili.nome')->toArray();
eq('formatter salvato nello schema', $schema['formatter'] ?? null, 'immobili.nome');
eq('name preservato', $schema['name'] ?? null, 'nome');

// il metodo morto callback() non deve più esistere
eq('callback() rimosso', method_exists(TableColumn::class, 'callback'), false);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
