<?php
/** php tests/Sql/QueryCounterTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\App\Debug;
use Wonder\Sql\QueryCounter;

function counterDebug(bool $on): void {
    $_ENV['APP_DEBUG'] = $on ? '1' : '0';
    unset($_ENV['QUERY_COUNTER_TOP'], $_SERVER['QUERY_COUNTER_TOP']);
    Debug::reset();
    QueryCounter::reset();
}

check('debug spento: nessun conteggio, nessun report', function () {
    counterDebug(false);
    QueryCounter::note("SELECT * FROM `user` WHERE id = 1");
    $captured = [];
    QueryCounter::setSink(function (string $l) use (&$captured) { $captured[] = $l; });
    QueryCounter::report();
    return QueryCounter::total() === 0 && QueryCounter::groups() === [] && $captured === [];
});

check('stessa forma con valori diversi: un solo gruppo', function () {
    counterDebug(true);
    QueryCounter::note("SELECT * FROM `user` WHERE `id` = '1' LIMIT 1");
    QueryCounter::note("SELECT * FROM `user` WHERE `id` = '27' LIMIT 1");
    QueryCounter::note("SHOW TABLES LIKE 'seo'");
    return QueryCounter::total() === 3
        && QueryCounter::groups() === [
            'SELECT * FROM `user` WHERE `id` = ? LIMIT ?' => 2,
            'SHOW TABLES LIKE ?' => 1,
        ];
});

check('normalize: apici con escape, IN, numeri, spazi', function () {
    return QueryCounter::normalize("SELECT  a\n FROM t1 WHERE b = 'x\\'y' AND c IN (1, 2, 3) AND d = -4.5")
        === 'SELECT a FROM t1 WHERE b = ? AND c IN (?) AND d = ?';
});

check('report: totale, uri e gruppi in ordine', function () {
    counterDebug(true);
    $_SERVER['REQUEST_URI'] = '/prodotti/';
    QueryCounter::note("SHOW TABLES LIKE 'a'");
    QueryCounter::note("SHOW TABLES LIKE 'b'");
    QueryCounter::note("SELECT 1");
    $captured = [];
    QueryCounter::setSink(function (string $l) use (&$captured) { $captured[] = $l; });
    QueryCounter::report();
    unset($_SERVER['REQUEST_URI']);
    return count($captured) === 1
        && $captured[0] === '[SQL] /prodotti/ queries=3 top=2x SHOW TABLES LIKE ? | 1x SELECT ?';
});

check('QUERY_COUNTER_TOP limita i gruppi nel report', function () {
    counterDebug(true);
    $_ENV['QUERY_COUNTER_TOP'] = '1';
    QueryCounter::note("SELECT 1");
    QueryCounter::note("SHOW TABLES LIKE 'a'");
    QueryCounter::note("SHOW TABLES LIKE 'b'");
    $captured = [];
    QueryCounter::setSink(function (string $l) use (&$captured) { $captured[] = $l; });
    QueryCounter::report();
    return str_ends_with($captured[0], 'queries=3 top=2x SHOW TABLES LIKE ?');
});

check('reset azzera conteggi e gruppi', function () {
    counterDebug(true);
    QueryCounter::note("SELECT 1");
    QueryCounter::reset();
    return QueryCounter::total() === 0 && QueryCounter::groups() === [];
});

counterDebug(false);

summary();
