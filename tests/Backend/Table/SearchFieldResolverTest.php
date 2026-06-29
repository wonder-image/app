<?php
/** php tests/Backend/Table/SearchFieldResolverTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\Backend\Support\ResourceTableRenderer;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

$foreignMap = [
    'user' => ['local_key' => 'user_id', 'foreign_key' => 'id'],
];

// plain fields untouched
eq('plain only',
    ResourceTableRenderer::resolveSearchFields(['name', 'surname'], $foreignMap),
    ['name', 'surname']
);

// dotted resolves; multiple columns on same relation merge
eq('dotted merged',
    ResourceTableRenderer::resolveSearchFields(['name', 'user.email', 'user.username'], $foreignMap),
    ['name', ['table' => 'user', 'local_key' => 'user_id', 'foreign_key' => 'id', 'columns' => ['email', 'username']]]
);

// unknown foreign table -> entry dropped (no FK to resolve)
eq('unknown relation dropped',
    ResourceTableRenderer::resolveSearchFields(['name', 'ghost.col'], $foreignMap),
    ['name']
);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
