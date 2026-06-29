<?php
/**
 * Standalone test (no phpunit in repo):
 *   php tests/Backend/Table/SearchWhereTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

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

// 1) plain columns, single word
check(
    'single word, main columns',
    SSP::buildSearchWhere('mario', ['name', 'surname']),
    "(CONCAT_WS(' ', `name`, `surname`) LIKE '%mario%')"
);

// 2) plain columns, two words -> AND between words
check(
    'two words, main columns',
    SSP::buildSearchWhere('mario rossi', ['name', 'surname']),
    "(CONCAT_WS(' ', `name`, `surname`) LIKE '%mario%') AND (CONCAT_WS(' ', `name`, `surname`) LIKE '%rossi%')"
);

// 3) one relation descriptor + main columns, single word
$relation = ['table' => 'user', 'local_key' => 'user_id', 'foreign_key' => 'id', 'columns' => ['email', 'username']];
check(
    'relation + main, single word',
    SSP::buildSearchWhere('mario', ['name', $relation]),
    "((CONCAT_WS(' ', `name`) LIKE '%mario%') OR (`user_id` IN (SELECT `id` FROM `user` WHERE CONCAT_WS(' ', `email`, `username`) LIKE '%mario%')))"
);

// 4) only a relation, no main columns
check(
    'relation only',
    SSP::buildSearchWhere('mario', [$relation]),
    "(`user_id` IN (SELECT `id` FROM `user` WHERE CONCAT_WS(' ', `email`, `username`) LIKE '%mario%'))"
);

// 5) empty search value -> empty string
check('empty value', SSP::buildSearchWhere('', ['name']), '');

// 6) no usable fields -> empty string
check('no fields', SSP::buildSearchWhere('mario', []), '');

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
