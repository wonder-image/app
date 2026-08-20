<?php
/**
 * Standalone test (no phpunit in repo):
 *   php tests/Security/SqlQueryHardeningTest.php
 *
 * Root-level hardening of Wonder\Sql\Query: the two pure helpers that protect
 * identifier positions (table / column names) and the numeric LIMIT clause.
 * These are the only Query surfaces unit-testable without a live DB connection
 * (the query methods connect in the constructor).
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Wonder\Sql\Query;

$fail = 0;
function check(string $label, $got, $expected) {
    global $fail;
    if ($got !== $expected) {
        $fail++;
        echo "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  got:      " . var_export($got, true) . "\n";
    } else {
        echo "ok: $label\n";
    }
}

// --- escapeIdentifier: bare identifiers unchanged, backticks doubled -------
check('identifier plain', Query::escapeIdentifier('users'), '`users`');
check('identifier backtick breakout neutralised', Query::escapeIdentifier('u`) ;DROP TABLE x-- '), '`u``) ;DROP TABLE x-- `');

// --- sanitizeLimit: only digits and the "offset, count" form survive ------
check('limit int', Query::sanitizeLimit(5), '5');
check('limit numeric string', Query::sanitizeLimit('10'), '10');
check('limit offset,count', Query::sanitizeLimit('0, 25'), '0, 25');
check('limit null -> empty', Query::sanitizeLimit(null), '');
check('limit empty -> empty', Query::sanitizeLimit(''), '');
check('limit injection dropped', Query::sanitizeLimit('1; DROP TABLE users-- '), '');
check('limit union injection dropped', Query::sanitizeLimit('1 UNION SELECT password FROM admin'), '');
check('limit subquery injection dropped', Query::sanitizeLimit('1 PROCEDURE ANALYSE(EXTRACTVALUE(1,CONCAT(0x3a,VERSION())),1)'), '');

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
