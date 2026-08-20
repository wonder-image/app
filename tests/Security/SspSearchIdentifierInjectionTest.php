<?php
/**
 * Standalone test (no phpunit in repo):
 *   php tests/Security/SspSearchIdentifierInjectionTest.php
 *
 * Regression guard: SSP::buildSearchWhere() interpolates column / table /
 * key identifiers that originate from the client-round-tripped
 * `config.search_columns` blob. Every identifier must be backtick-escaped so a
 * tampered identifier cannot break out of the quoted context.
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

// 1) benign identifiers are unchanged (backtick-quoted exactly as before).
check(
    'benign main columns unchanged',
    SSP::buildSearchWhere('mario', ['name', 'surname']),
    "(CONCAT_WS(' ', `name`, `surname`) LIKE '%mario%')"
);

// 2) a main column carrying a backtick can no longer break out: the backtick
//    is doubled and stays inside the quoted identifier.
check(
    'main column backtick breakout neutralised',
    SSP::buildSearchWhere('x', ['id`)-- ']),
    "(CONCAT_WS(' ', `id``)-- `) LIKE '%x%')"
);

// 3) a relation descriptor with malicious table / keys / columns is fully
//    escaped in every interpolated identifier position.
$evilRelation = [
    'table'       => 'user` WHERE 1=1 UNION SELECT password FROM admin-- ',
    'local_key'   => 'uid`',
    'foreign_key' => 'id`',
    'columns'     => ['email`'],
];
check(
    'relation identifiers fully escaped',
    SSP::buildSearchWhere('x', [$evilRelation]),
    "(`uid``` IN (SELECT `id``` FROM `user`` WHERE 1=1 UNION SELECT password FROM admin-- ` WHERE CONCAT_WS(' ', `email```) LIKE '%x%'))"
);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
