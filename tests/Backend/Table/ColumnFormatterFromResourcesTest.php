<?php
/** php tests/Backend/Table/ColumnFormatterFromResourcesTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\App\ResourceSchema\TableColumn;
use Wonder\Backend\Table\ColumnFormatterRegistry;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got: $g\n"; }
    else { echo "ok: $label\n"; }
}

// resource fittizia duck-typed (slug + tableSchema con formatter closure)
final class WiTestFmtResource {
    public static function slug(): string { return 'testfmt'; }
    public static function tableSchema(): array {
        return [
            TableColumn::key('prezzo')->formatter(static fn (array $row): string => 'X-' . ($row['prezzo'] ?? '')),
            TableColumn::key('nome')->text(), // niente formatter: ignorata
        ];
    }
}

ColumnFormatterRegistry::reset();

// la registrazione esplicita per nome resta
ColumnFormatterRegistry::register('x.named', static fn (array $r): string => 'N');
eq('named still works', ColumnFormatterRegistry::call('x.named', []), 'N');

// scan di una resource: closure inline registrata sotto {slug}.{colonna}
ColumnFormatterRegistry::registerFromResource(WiTestFmtResource::class);
eq('inline registered under derived key', ColumnFormatterRegistry::has('testfmt.prezzo'), true);
eq('inline runs with row', ColumnFormatterRegistry::call('testfmt.prezzo', ['prezzo' => 100]), 'X-100');
eq('column without formatter not registered', ColumnFormatterRegistry::has('testfmt.nome'), false);
eq('unknown derived key -> empty', ColumnFormatterRegistry::call('testfmt.mancante', []), '');

// reset azzera tutto
ColumnFormatterRegistry::reset();
eq('reset clears', ColumnFormatterRegistry::has('testfmt.prezzo'), false);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
