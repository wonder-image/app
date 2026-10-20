<?php
/** php tests/Security/SspSearchBindingTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';
require __DIR__.'/../../app/function/string/common.php';
require __DIR__.'/../../app/function/string/sanitize.php';
require __DIR__.'/../../app/config/array/characters.php';

use Wonder\Backend\Table\SSP;
use Wonder\Sql\Query;

// Stesso comportamento di mysqli::real_escape_string con charset a byte singolo.
$binder = Query::withEscaper(static fn (string $value): string => strtr($value, [
    '\\' => '\\\\', "\0" => '\\0', "\n" => '\\n', "\r" => '\\r', "'" => "\\'", '"' => '\\"', "\x1a" => '\\Z',
]));

$search = static fn (string $value): array => ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => $value]];
$values = static fn (array $bindings): array => array_column($bindings, 'val', 'key');

check('termine con apice: un segnaposto e il valore nella forma di sanitize()', static function () use ($search, $values): bool {
    $bindings = [];
    $where = SSP::filter($search("x'OR/**/1=1#"), ['name'], $bindings);

    // $CHARACTERS codifica l'apice come &apos;, come nei dati salvati.
    return $where === "WHERE (CONCAT_WS(' ', `name`) LIKE :binding_0)"
        && $values($bindings) === [':binding_0' => '%x&apos;OR/**/1=1#%'];
});

check('apice non codificato (senza $CHARACTERS): resta dentro il letterale escapato', static function () use ($binder, $search, $values): bool {
    $saved = $GLOBALS['CHARACTERS'];
    $GLOBALS['CHARACTERS'] = [];

    try {
        $bindings = [];
        $where = SSP::filter($search("x'OR/**/1=1#"), ['name'], $bindings);
        $sql = SSP::complexQueries($binder, $search("x'OR/**/1=1#"), 'users', 'id', [], ['name']);
    } finally {
        $GLOBALS['CHARACTERS'] = $saved;
    }

    return !str_contains($where, "'%")
        && $values($bindings) === [':binding_0' => "%x'OR/**/1=1#%"]
        && str_contains($sql['data'], "LIKE '%x\\'OR/**/1=1#%'")
        && str_contains($sql['filtered'], "LIKE '%x\\'OR/**/1=1#%'");
});

check('100% cerca il letterale 100%', static function () use ($binder, $search, $values): bool {
    $bindings = [];
    SSP::filter($search('100%'), ['price'], $bindings);
    $sql = SSP::complexQueries($binder, $search('100%'), 'users', 'id', [], ['price']);

    return $values($bindings) === [':binding_0' => '%100\\%%']
        && str_contains($sql['data'], "LIKE '%100\\\\%%'");
});

check('underscore escapato', static function () use ($search, $values): bool {
    $bindings = [];
    SSP::filter($search('a_b'), ['code'], $bindings);

    return $values($bindings) === [':binding_0' => '%a\\_b%'];
});

check('carattere di $CHARACTERS bindato nella forma salvata in tabella', static function () use ($search, $values): bool {
    $bindings = [];
    SSP::filter($search('caffè'), ['name'], $bindings);

    return $values($bindings) === [':binding_0' => '%caff&egrave;%'];
});

check('barra inversa e virgolette: tolte solo le barre di addslashes', static function () use ($search, $values): bool {
    $bindings = [];
    SSP::filter($search('a\\b"c'), ['name'], $bindings);

    return $values($bindings) === [':binding_0' => '%a\\b"c%'];
});

check('due relazioni: tre binding distinti per parola', static function () use ($values): bool {
    $relation = static fn (string $table): array => ['table' => $table, 'local_key' => $table.'_id', 'foreign_key' => 'id', 'columns' => ['name']];
    $bindings = [];
    $where = SSP::buildSearchWhere('mario rossi', ['name', $relation('a'), $relation('b')], $bindings);
    $keys = array_keys($values($bindings));

    return count($bindings) === 6
        && count(array_unique($keys)) === 6
        && substr_count($where, ':binding_') === 6
        && $values($bindings)[':binding_0'] === '%mario%'
        && $values($bindings)[':binding_3'] === '%rossi%';
});

check('relazione annidata: ogni LIKE ha il suo segnaposto', static function (): bool {
    $nested = ['table' => 'p', 'local_key' => 'pid', 'foreign_key' => 'id', 'columns' => ['title'],
        'relations' => [['table' => 'q', 'local_key' => 'qid', 'foreign_key' => 'id', 'columns' => ['code']]]];
    $bindings = [];
    $where = SSP::buildSearchWhere('x', [$nested], $bindings);

    return count($bindings) === 2
        && str_contains($where, 'LIKE :binding_0')
        && str_contains($where, 'LIKE :binding_1');
});

check('i binding della ricerca non entrano nel conteggio totale', static function () use ($binder, $search): bool {
    $sql = SSP::complexQueries($binder, $search('mario'), 'users', 'id', [], ['name'], null, '`deleted` = 0');

    return str_contains($sql['data'], "LIKE '%mario%'")
        && str_contains($sql['filtered'], "LIKE '%mario%'")
        && !str_contains($sql['total'], 'LIKE')
        && str_ends_with($sql['total'], 'WHERE `deleted` = 0');
});

check('add_bindings scrive su $a (mappa diretta e vecchio formato)', static function (): bool {
    $a = [];
    SSP::add_bindings($a, [':uno' => 1, 'due' => 'x', 0 => 3]);
    SSP::add_bindings($a, ['bindings' => ['tre' => null]]);

    return array_column($a, 'val', 'key') === [':uno' => 1, ':due' => 'x', 0 => 3, ':tre' => null];
});

check('whereResult con binding: valori legati nella condizione', static function () use ($binder, $search): bool {
    $sql = SSP::complexQueries($binder, $search('mario'), 'users', 'id', [], ['name'],
        ['condition' => '`area` = :area AND `level` > ?', 'bindings' => [':area' => "o'k", 0 => 2]]);

    return str_contains($sql['data'], "WHERE (CONCAT_WS(' ', `name`) LIKE '%mario%') AND `area` = 'o\\'k' AND `level` > 2");
});

check('whereAll con binding: valori legati in dati e totale', static function () use ($binder, $search): bool {
    $sql = SSP::complexQueries($binder, $search(''), 'users', 'id', [], ['name'], null,
        ['condition' => '`deleted` = :d', 'bindings' => ['d' => 'false']]);

    return str_contains($sql['data'], "WHERE `deleted` = 'false'")
        && str_ends_with($sql['total'], "WHERE `deleted` = 'false'");
});

check('frammenti firmati senza binding non passano dallo scanner', static function () use ($binder, $search): bool {
    $sql = SSP::complexQueries($binder, $search('mario'), 'users', 'id', [], ['name'], '`a` = :non_legato', '`b` = ?');

    return str_contains($sql['data'], '`a` = :non_legato AND `b` = ?')
        && str_ends_with($sql['total'], 'WHERE `b` = ?');
});

check('senza ricerca nessun binding', static function () use ($search): bool {
    $bindings = [];

    return SSP::filter($search(''), ['name'], $bindings) === '' && $bindings === [];
});

check('bindParams: nomi senza due punti, interi in ordine', static fn () =>
    SSP::bindParams([['key' => ':a', 'val' => 1], ['key' => 0, 'val' => 'x'], ['key' => 1, 'val' => 'y']]) === ['a' => 1, 0 => 'x', 1 => 'y']);

summary();
