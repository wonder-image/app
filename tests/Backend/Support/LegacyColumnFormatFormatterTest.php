<?php
/** php tests/Backend/Support/LegacyColumnFormatFormatterTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\Backend\Support\ResourceTableRenderer;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    if ($got !== $expected) { $fail++; echo "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  got: " . var_export($got, true) . "\n"; }
    else { echo "ok: $label\n"; }
}

$rc = new ReflectionClass(ResourceTableRenderer::class);
$renderer = $rc->newInstanceWithoutConstructor();
$slugProp = $rc->getProperty('slug'); $slugProp->setValue($renderer, 'testfmt');
$m = $rc->getMethod('legacyColumnFormat');

// closure -> chiave derivata (stringa), mai la closure
$outClosure = $m->invoke($renderer, ['name' => 'prezzo', 'type' => 'text', 'formatter' => static fn (array $r): string => 'X']);
eq('closure -> derived key string', $outClosure['formatter'] ?? null, 'testfmt.prezzo');
eq('formatter is string not closure', is_string($outClosure['formatter'] ?? null), true);

// stringa -> invariata
$outString = $m->invoke($renderer, ['name' => 'prezzo', 'type' => 'text', 'formatter' => 'immobili.prezzo']);
eq('string formatter unchanged', $outString['formatter'] ?? null, 'immobili.prezzo');

// nessun formatter -> chiave assente
$outNone = $m->invoke($renderer, ['name' => 'prezzo', 'type' => 'text']);
eq('no formatter key', array_key_exists('formatter', $outNone), false);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
