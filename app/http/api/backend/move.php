<?php

use Wonder\App\Support\ApiRequest;

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$table = ApiRequest::string('table');
$rowId = ApiRequest::int('id');
$action = ApiRequest::string('action');

if ($table === '' || $rowId <= 0 || !in_array($action, ['up', 'down'], true)) {
    ApiRequest::error('Parametri non validi.', 422);
}

ApiRequest::selectDatabase(ApiRequest::string('database', 'main'));

$filter = ApiRequest::string('filter');
$filterId = ApiRequest::string('filter_id');

$query = sqlSelect($table, ['id' => $rowId], 1);

if (!($query->exists ?? false)) {
    ApiRequest::error('Riga non trovata.', 404);
}

$oldPosition = (int) ($query->row['position'] ?? 0);

if ($oldPosition <= 0) {
    ApiRequest::error('Posizione non valida.', 422);
}

if ($filter === '' && $filterId === '') {
    $tableKey = strtoupper($table);
    $filter = $TABLE->$tableKey['position']['input']['filter'] ?? null;
    $filterId = $filter ? (string) ($query->row[$filter] ?? '') : '';
}

$newPosition = $action === 'up' ? $oldPosition - 1 : $oldPosition + 1;

if ($filter !== null && $filter !== '' && $filterId !== '') {
    $oldPositionId = sqlSelect($table, ['position' => $newPosition, $filter => $filterId, 'deleted' => 'false'], 1)->id;

    if (!empty($oldPositionId)) {
        sqlModify($table, ['position' => $oldPosition, 'deleted' => 'false'], 'id', $oldPositionId);
    }
} else {
    sqlModify($table, ['position' => $oldPosition, 'deleted' => 'false'], 'position', $newPosition);
}

sqlModify($table, ['position' => $newPosition, 'deleted' => 'false'], 'id', $rowId);

ApiRequest::success('Posizione aggiornata.', [
    'table' => $table,
    'id' => $rowId,
    'position' => $newPosition,
]);
