<?php

use Wonder\App\Support\ApiRequest;

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$routeMeta = is_array($ROUTE_META ?? null) ? $ROUTE_META : [];
$table = ApiRequest::string('table');
$column = trim((string) (($routeMeta['legacy_column'] ?? null) ?: ApiRequest::string('column')));
$id = ApiRequest::int('id');

if ($table === '' || $column === '' || $id <= 0) {
    ApiRequest::error('Parametri non validi.', 422);
}

ApiRequest::selectDatabase(ApiRequest::string('database', 'main'));

$query = sqlSelect($table, ['id' => $id], 1);
$row = is_array($query->row ?? null) ? $query->row : [];

if ($row === []) {
    ApiRequest::error('Riga non trovata.', 404);
}

$bool = (($row[$column] ?? 'false') === 'true') ? 'false' : 'true';
sqlModify($table, [$column => $bool], 'id', $id);

ApiRequest::success('Stato aggiornato.', [
    'value' => $bool,
    'table' => $table,
    'column' => $column,
    'id' => $id,
]);
