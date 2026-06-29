<?php

use Wonder\App\Support\ApiRequest;

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$table = ApiRequest::string('table');
$id = ApiRequest::int('id');

if ($table === '' || $id <= 0) {
    ApiRequest::error('Parametri non validi.', 422);
}

ApiRequest::selectDatabase(ApiRequest::string('database', 'main'));

$position = null;
$filter = null;
$filterId = null;

if (sqlColumnExists($table, 'position')) {
    $query = sqlSelect($table, ['id' => $id], 1);

    if (!($query->exists ?? false)) {
        ApiRequest::error('Riga non trovata.', 404);
    }

    $position = $query->row['position'] ?? null;

    $tableKey = strtoupper($table);
    $filter = $TABLE->$tableKey['position']['input']['filter'] ?? null;
    $filterId = $filter ? ($query->row[$filter] ?? null) : null;
}

if (sqlColumnExists($table, 'active')) {
    $values = [
        'active' => 'false',
        'deleted' => 'true',
    ];
} elseif (sqlColumnExists($table, 'visible')) {
    $values = [
        'visible' => 'false',
        'deleted' => 'true',
    ];
} else {
    $values = [
        'deleted' => 'true',
    ];
}

sqlModify($table, $values, 'id', $id);

if ($position !== null && sqlColumnExists($table, 'position')) {
    if ($filter !== null && $filterId !== null) {
        $sql = sqlSelect($table, [$filter => $filterId, 'deleted' => 'false']);
    } else {
        $sql = sqlSelect($table, ['deleted' => 'false']);
    }

    foreach ($sql->row as $row) {
        if (($row['position'] ?? null) === null || $row['position'] <= $position) {
            continue;
        }

        sqlModify($table, ['position' => $row['position'] - 1], 'id', $row['id']);
    }
}

ApiRequest::success('Riga eliminata.', [
    'table' => $table,
    'id' => $id,
]);
