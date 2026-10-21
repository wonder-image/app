<?php
/** php tests/App/Models/PopupConditionTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';
require __DIR__ . '/../../Sql/SqlLikeEmulator.php';

use Wonder\App\Models\Communications\Popup;
use Wonder\Sql\Query;

// Escape della connessione di default (come real_escape_string) e di una con NO_BACKSLASH_ESCAPES.
$standard = Query::withEscaper(static fn (string $v): string => addcslashes($v, "\\\0\n\r'\"\x1a"));
$noBackslash = Query::withEscaper(static fn (string $v): string => str_replace("'", "''", $v));

check('pageKeyCondition: chiave normale, _ di __all__ escapati', function () use ($standard) {
    return Popup::pageKeyCondition('contact', $standard)
        === "(pages LIKE '%\\\"contact\\\"%' OR pages LIKE '%\\\"\\\\_\\\\_all\\\\_\\\\_\\\"%') AND visible = 'true'";
});

check('pageKeyCondition: % e _ della chiave non sono jolly del LIKE', function () use ($standard) {
    $sql = Popup::pageKeyCondition('product_detail', $standard);
    $tutte = Popup::pageKeyCondition('x', $standard);

    return sqlLikeMatches($sql, '["home","product_detail"]')
        && !sqlLikeMatches($sql, '["productXdetail"]')
        && !sqlLikeMatches(Popup::pageKeyCondition('a%', $standard), '["abc"]')
        && sqlLikeMatches(Popup::pageKeyCondition('a%', $standard), '["a%"]')
        && sqlLikeMatches($tutte, '["__all__"]', 1)
        && !sqlLikeMatches($tutte, '["XXallXX"]', 1);
});

check('pageKeyCondition: un apice non chiude la stringa, anche con NO_BACKSLASH_ESCAPES', function () use ($standard, $noBackslash) {
    $chiave = "x\"' OR 1=1 -- ";

    return Popup::pageKeyCondition($chiave, $noBackslash)
            === "(pages LIKE '%\"x\"'' OR 1=1 -- \"%' OR pages LIKE '%\"\\_\\_all\\_\\_\"%') AND visible = 'true'"
        && Popup::pageKeyCondition($chiave, $standard)
            === "(pages LIKE '%\\\"x\\\"\\' OR 1=1 -- \\\"%' OR pages LIKE '%\\\"\\\\_\\\\_all\\\\_\\\\_\\\"%') AND visible = 'true'";
});

check('il codice non usa più addslashes', function () {
    return !str_contains((string) file_get_contents(__DIR__ . '/../../../class/App/Models/Communications/Popup.php'), 'addslashes(');
});

summary();
