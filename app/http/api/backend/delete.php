<?php

use Wonder\App\LegacyGlobals;
use Wonder\App\Support\ApiRequest;
use Wonder\App\Visibility\RowVisibility;
use Wonder\Backend\Table\TableAuthorization;

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$table = ApiRequest::string('table');

if ($table !== '' && \Wonder\App\Support\SyncedTables::isReadonly($table)) {
    ApiRequest::error('Tabella in sola lettura in questo ambiente.', 403);
}

$id = ApiRequest::int('id');

if ($table === '' || $id <= 0) {
    ApiRequest::error('Parametri non validi.', 422);
}

// Permessi per tabella prima di qualsiasi query (Resource o solo admin).
if (!TableAuthorization::make()->allows($table, TableAuthorization::DELETE, TableAuthorization::authoritiesOf(LegacyGlobals::get('USER')))) {
    ApiRequest::error('Operazione non consentita.', 403);
}

ApiRequest::selectDatabase(ApiRequest::string('database', 'main'));

// Riga che l'utente non vede: come se non esistesse.
if (!RowVisibility::visible($table, $id, RowVisibility::currentUser())) {
    ApiRequest::error('Riga non trovata.', 404);
}

$resourceClass = \Wonder\App\ResourceRegistry::resolveByTable($table);

if ($resourceClass !== null) {
    try {
        $resourceClass::assertDeletable($id);
    } catch (RuntimeException $exception) {
        ApiRequest::error($exception->getMessage(), 422);
    }
}

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

\Wonder\App\Support\SyncedTables::exportIfSynced($table);

ApiRequest::success('Riga eliminata.', [
    'table' => $table,
    'id' => $id,
]);
