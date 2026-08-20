<?php
/**
 * Standalone test (no phpunit in repo):
 *   php tests/Security/SqlPositionReorderConditionTest.php
 *
 * sqlPositionReorderCondition() builds the WHERE used by formToArray() to
 * shift sibling `position` rows on update. The filter value is re-read from
 * the database (originally user input) and was previously interpolated raw
 * into a string condition -> second-order SQL injection.
 *
 * The first assertion pins BYTE-IDENTICAL output for legitimate data, proving
 * the hardening does not change behaviour for real rows in production.
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../app/function/sql.php';

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

// 1) legitimate data: output must be exactly what the old raw interpolation
//    produced ("`category_id` = '7' AND `position` > 3 AND `deleted` = 'false'").
check(
    'legit input is byte-identical to legacy output',
    sqlPositionReorderCondition('category_id', '7', 3),
    "`category_id` = '7' AND `position` > 3 AND `deleted` = 'false'"
);

// 2) a filter value that legitimately contains a quote (e.g. O'Brien) now
//    matches correctly instead of producing broken/injectable SQL.
check(
    'quoted value is escaped, not broken',
    sqlPositionReorderCondition('name', "O'Brien", 3),
    "`name` = 'O\\'Brien' AND `position` > 3 AND `deleted` = 'false'"
);

// 3) injection through the filter value is neutralised.
check(
    'value injection neutralised',
    sqlPositionReorderCondition('name', "1' OR '1'='1", 0),
    "`name` = '1\\' OR \\'1\\'=\\'1' AND `position` > 0 AND `deleted` = 'false'"
);

// 4) injection through the filter column identifier is neutralised.
check(
    'column injection neutralised',
    sqlPositionReorderCondition('name`-- ', '7', 3),
    "`name``-- ` = '7' AND `position` > 3 AND `deleted` = 'false'"
);

// 5) injection through the position value is neutralised (cast to int).
check(
    'position injection neutralised',
    sqlPositionReorderCondition('cat', '7', '3 OR 1=1'),
    "`cat` = '7' AND `position` > 3 AND `deleted` = 'false'"
);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
