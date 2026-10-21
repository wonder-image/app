<?php
/** php tests/Security/SspTableIdentifierInjectionTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Backend\Table\SSP;
use Wonder\Sql\Query;

$binder = Query::withEscaper(static fn (string $value): string => addslashes($value));
$request = ['draw' => 1, 'start' => 0, 'length' => 25];

// Le stringhe che complex() eseguiva prima della modifica (stesso spazio bianco).
$today = static function (string $table, string $pk): array {
    $i16 = "\n".str_repeat(' ', 16);
    $i20 = "\n".str_repeat(' ', 20);

    return [
        'data'     => 'SELECT *'.$i16.'FROM `'.$table.'`'.$i16.$i16.$i16.$i16.'LIMIT 0, 25',
        'filtered' => 'SELECT COUNT(`'.$pk.'`)'.$i20.'FROM   `'.$table.'`'.$i20,
        'total'    => 'SELECT COUNT(`'.$pk.'`)'.$i20.'FROM   `'.$table.'` ',
    ];
};

foreach ([['users', 'id'], ['gst_product_models', 'id'], ['tab_2026_x9', 'id_riga']] as [$table, $pk]) {
    check("SQL identico a oggi per {$table}", static fn () =>
        SSP::complexQueries($binder, $request, $table, $pk, []) === $today($table, $pk));
}

check('con group by la tabella è escapata nelle subquery di conteggio', static function () use ($binder, $request): bool {
    $sql = SSP::complexQueries($binder, $request, 'gst_movements', 'id', [], [], null, '`deleted` = 0', null, null, '`a`, COUNT(*) AS n', '`a`');

    return $sql['filtered'] === 'SELECT COUNT(*) FROM (SELECT 1 FROM `gst_movements` WHERE `deleted` = 0 GROUP BY `a`) `wi_grouped`'
        && $sql['total'] === 'SELECT COUNT(*) FROM (SELECT 1 FROM `gst_movements` WHERE `deleted` = 0 GROUP BY `a`) `wi_grouped`';
});

$evil = 'x` UNION SELECT 1 -- ';
$sql = SSP::complexQueries($binder, $request, $evil, $evil, []);

check('tabella malevola resta in un solo identificatore', static function () use ($sql): bool {
    foreach ($sql as $query) {
        if (preg_match('/FROM +`x`` UNION SELECT 1 -- `/', $query) !== 1) {
            return false;
        }
        // Tolto l'identificatore escapato non resta nessuna UNION.
        if (str_contains(str_replace('`x`` UNION SELECT 1 -- `', '', $query), 'UNION')) {
            return false;
        }
    }

    return true;
});

check('chiave primaria malevola resta in un solo identificatore', static fn () =>
    str_starts_with($sql['filtered'], 'SELECT COUNT(`x`` UNION SELECT 1 -- `)')
    && str_starts_with($sql['total'], 'SELECT COUNT(`x`` UNION SELECT 1 -- `)'));

check('SSP::escapeIdentifier delega a Query::escapeIdentifier', static fn () =>
    SSP::escapeIdentifier('a`b') === Query::escapeIdentifier('a`b') && SSP::escapeIdentifier('a`b') === '`a``b`');

summary();
