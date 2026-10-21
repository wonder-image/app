<?php
/** php tests/Sql/QueryBindTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';
require __DIR__.'/../../app/function/sql.php';

use Wonder\Sql\Query;

// Stesso comportamento di mysqli::real_escape_string con charset a byte singolo.
$query = Query::withEscaper(static fn (string $value): string => strtr($value, [
    '\\' => '\\\\', "\0" => '\\0', "\n" => '\\n', "\r" => '\\r', "'" => "\\'", '"' => '\\"', "\x1a" => '\\Z',
]));

$throws = static function (callable $callback): bool {
    try {
        $callback();
    } catch (RuntimeException) {
        return true;
    }

    return false;
};

check('esempio del piano: posizionale e nominale', function () use ($query) {
    return $query->bind("`id` = ? AND `name` = :n", [5, 'n' => "x'y"])
        === "`id` = 5 AND `name` = 'x\\'y'";
});

check('NULL, interi, float, booleani', function () use ($query) {
    return $query->bind('a = ? AND b = ? AND c = ? AND d = ? AND e = ?', [null, -7, 1.5, true, false])
        === 'a = NULL AND b = -7 AND c = 1.5 AND d = 1 AND e = 0';
});

check('chiave nominale accettata anche con i due punti', function () use ($query) {
    return $query->bind('x = :a', [':a' => 'v']) === "x = 'v'";
});

check('nome ripetuto sostituito ovunque', function () use ($query) {
    return $query->bind('a = :v OR b = :v', ['v' => 2]) === 'a = 2 OR b = 2';
});

check(':binding_1 non tocca :binding_10', function () use ($query) {
    return $query->bind('a = :binding_1 AND b = :binding_10', [':binding_1' => 1, ':binding_10' => 10])
        === 'a = 1 AND b = 10';
});

check('? e :nome dentro letterali, identificatori e commenti restano intatti', function () use ($query) {
    $sql = "SELECT '?', 'a:b', \"?\", `col?`, `x:y` FROM t /* ? :c */ WHERE a = ? -- ? :d\n AND b = :e # ? :f\n";
    $expected = "SELECT '?', 'a:b', \"?\", `col?`, `x:y` FROM t /* ? :c */ WHERE a = 1 -- ? :d\n AND b = 'e' # ? :f\n";

    return $query->bind($sql, [1, 'e' => 'e']) === $expected;
});

check('apici raddoppiati e backslash nei letterali non chiudono la stringa', function () use ($query) {
    $sql = "SELECT 'it''s ?', 'a\\' ?', `a``?` WHERE x = ?";

    return $query->bind($sql, [3]) === "SELECT 'it''s ?', 'a\\' ?', `a``?` WHERE x = 3";
});

check('valori stringa pericolosi restano un solo letterale', function () use ($query) {
    return $query->bind('a = ?', ["x' OR '1'='1"]) === "a = 'x\\' OR \\'1\\'=\\'1'"
        && $query->bind('a = ?', ["\\'; DROP TABLE t; -- "]) === "a = '\\\\\\'; DROP TABLE t; -- '"
        && $query->bind('a = ?', ['']) === "a = ''";
});

check('il valore sostituito non viene riletto come segnaposto', function () use ($query) {
    return $query->bind('a = ? AND b = :n', ['?', 'n' => ':n']) === "a = '?' AND b = ':n'";
});

check('segnaposto senza valore → eccezione', function () use ($query, $throws) {
    return $throws(fn () => $query->bind('a = ? AND b = ?', [1]))
        && $throws(fn () => $query->bind('a = :x', ['y' => 1]))
        && $throws(fn () => $query->bind('a = :x', []));
});

check('valori senza segnaposto → eccezione', function () use ($query, $throws) {
    return $throws(fn () => $query->bind('a = ?', [1, 2]))
        && $throws(fn () => $query->bind('a = 1', ['x' => 1]));
});

check('tipi non scalari e float non finiti → eccezione', function () use ($query, $throws) {
    return $throws(fn () => $query->bind('a = ?', [[1]]))
        && $throws(fn () => $query->bind('a = ?', [new stdClass()]))
        && $throws(fn () => $query->bind('a = ?', [INF]))
        && $throws(fn () => $query->bind('a = ?', [NAN]))
        && $throws(fn () => $query->bind('a = :a', ['a' => [1]]))
        && $throws(fn () => $query->bind('a = :a', [':a' => new stdClass()]))
        && $throws(fn () => $query->bind('a = :a', ['a' => fopen('php://memory', 'r')]));
});

check('operatori :: e := non sono segnaposto', function () use ($query) {
    return $query->bind('SET @a := ?', [1]) === 'SET @a := 1';
});

check('caratteri fuori da latin1 trattati come in valueToSql', function () use ($query) {
    return $query->bind('a = ?', ['€']) === "a = '&#8364;'";
});

check('chiavi :a e a insieme → eccezione, non sovrascrittura silenziosa', function () use ($query, $throws) {
    return $throws(fn () => $query->bind('a = :a', [':a' => 1, 'a' => 2]))
        && $throws(fn () => $query->bind('a = :a', ['a' => 1, ':a' => 2]))
        && $query->bind('a = :a', [':a' => 1]) === 'a = 1'
        && $query->bind('a = :a', ['a' => 1]) === 'a = 1';
});

check('helper sqlBind() definito', fn () => function_exists('sqlBind'));

summary();
