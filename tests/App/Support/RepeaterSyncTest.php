<?php
/** php tests/App/Support/RepeaterSyncTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\ResourceSchema\RepeaterRelation;
use Wonder\App\Support\Repeater;

// Database finto: le funzioni sql*() registrano le scritture.
$GLOBALS['__rows'] = [];
$GLOBALS['__writes'] = [];

function sqlSelect($table, $condition = null, $limit = null, $order = null, $orderDirection = null, $attributes = '*'): object
{
    return (object) ['row' => $GLOBALS['__rows']];
}

function sqlModify($table, $values, $column, $value): object
{
    $GLOBALS['__writes'][] = ['modify', (string) $value, $values];

    return (object) ['success' => true];
}

function sqlInsert($table, $values): object
{
    $GLOBALS['__writes'][] = ['insert', null, $values];

    return (object) ['success' => true, 'insert_id' => 99];
}

function sqlDelete($table, $condition): bool
{
    $GLOBALS['__writes'][] = ['delete', null, $condition];

    return true;
}

check('righe esistenti aggiornate e non cancellate (id numerici)', function () {
    $GLOBALS['__rows'] = [
        ['id' => '1', 'parent_id' => '5', 'value' => 'a', 'deleted' => 'false'],
        ['id' => '2', 'parent_id' => '5', 'value' => 'b', 'deleted' => 'false'],
        ['id' => '3', 'parent_id' => '5', 'value' => 'c', 'deleted' => 'false'],
    ];
    $GLOBALS['__writes'] = [];

    $summary = Repeater::syncRelatedRows(
        RepeaterRelation::make('child_rows', 'parent_id')->positionKey('position'),
        5,
        [['id' => '1', 'value' => 'A'], ['id' => '2', 'value' => 'B'], ['value' => 'nuova']]
    );

    return $summary['updated'] === ['1', '2']
        && count($summary['inserted']) === 1
        && $summary['deleted'] === ['3']
        && !in_array(['modify', '1', ['deleted' => 'true']], $GLOBALS['__writes'], true)
        && in_array(['modify', '3', ['deleted' => 'true']], $GLOBALS['__writes'], true);
});

summary();
