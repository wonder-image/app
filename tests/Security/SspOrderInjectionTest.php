<?php
/**
 * Standalone test (no phpunit in repo):
 *   php tests/Security/SspOrderInjectionTest.php
 *
 * Regression guard: SSP::order() must not allow SQL injection through the
 * DataTables `order[0][name]` (column) and `order[0][dir]` (direction)
 * request parameters, which are client-controlled.
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Wonder\Backend\Table\SSP;

$fail = 0;
function check(string $label, $got, $expected) {
    global $fail;
    if ($got !== $expected) {
        $fail++;
        echo "FAIL: $label\n  expected: $expected\n  got:      $got\n";
    } else {
        echo "ok: $label\n";
    }
}

// 1) benign column + direction: unchanged behaviour
check('benign asc', SSP::order('name', 'asc'), "ORDER BY `name` ASC");
check('benign desc', SSP::order('name', 'desc'), "ORDER BY `name` DESC");

// 2) empty direction defaults to DESC (unchanged behaviour)
check('empty direction -> DESC', SSP::order('name', ''), "ORDER BY `name` DESC");

// 3) direction injection: anything that is not exactly ASC collapses to DESC
check(
    'direction injection collapses to DESC',
    SSP::order('id', 'DESC, (SELECT IF(1=1,SLEEP(5),0))'),
    "ORDER BY `id` DESC"
);
check(
    'direction injection (asc-prefixed) collapses to DESC',
    SSP::order('id', 'asc, (SELECT 1)'),
    "ORDER BY `id` DESC"
);

// 4) column identifier injection: backticks are doubled so the payload can
//    never break out of the quoted identifier.
check(
    'column backtick breakout is neutralised',
    SSP::order('id`,(SELECT 1)-- -', 'asc'),
    "ORDER BY `id``,(SELECT 1)-- -` ASC"
);

// 5) no column -> empty clause (unchanged behaviour)
check('no column -> empty', SSP::order('', 'asc'), "");

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
