<?php

use Wonder\App\Support\ApiRequest;

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$table = ApiRequest::string('table');
$column = ApiRequest::string('column');
$rowId = ApiRequest::int('row_id');
$oldId = ApiRequest::int('file_id', -1);
$action = ApiRequest::string('action');

if ($table === '' || $column === '' || $rowId <= 0 || $oldId < 0 || !in_array($action, ['up', 'down'], true)) {
    ApiRequest::error('Parametri non validi.', 422);
}

$query = sqlSelect($table, ['id' => $rowId], 1);

if (!($query->exists ?? false)) {
    ApiRequest::error('Riga non trovata.', 404);
}

$files = json_decode((string) ($query->row[$column] ?? '[]'), true);
$files = is_array($files) ? $files : [];
$newId = $action === 'up' ? $oldId - 1 : $oldId + 1;
$newArray = [];

foreach ($files as $id => $image) {
    $id = (int) $id;

    if ($id === $newId) {
        $newArray[$oldId] = $image;
        continue;
    }

    if ($id === $oldId) {
        $newArray[$newId] = $image;
        continue;
    }

    $newArray[$id] = $image;
}

ksort($newArray, SORT_NUMERIC);
sqlModify($table, [$column => json_encode($newArray)], 'id', $rowId);

ApiRequest::success('Ordine file aggiornato.', [
    'table' => $table,
    'column' => $column,
    'id' => $rowId,
]);
