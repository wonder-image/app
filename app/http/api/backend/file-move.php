<?php

use Wonder\App\LegacyGlobals;
use Wonder\App\Support\ApiRequest;
use Wonder\App\Visibility\RowVisibility;
use Wonder\Backend\Table\ListTableRequest;
use Wonder\Backend\Table\TableAuthorization;

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$table = ApiRequest::string('table');

$column = ApiRequest::string('column');

if ($table !== '' && \Wonder\App\Support\SyncedTables::isReadonlyColumn($table, $column)) {
    ApiRequest::error('Tabella in sola lettura in questo ambiente.', 403);
}
$rowId = ApiRequest::int('row_id');
$oldId = ApiRequest::int('file_id', -1);
$action = ApiRequest::string('action');

// Tabella registrata, senza calcolarne lo schema.
$table = ListTableRequest::table($table);

if ($table === null || $rowId <= 0 || $oldId < 0 || !in_array($action, ['up', 'down'], true)) {
    ApiRequest::error('Parametri non validi.', 422);
}

// Permessi per tabella prima di qualsiasi query (Resource o solo admin).
if (!TableAuthorization::make()->allows($table, TableAuthorization::UPDATE, TableAuthorization::authoritiesOf(LegacyGlobals::get('USER')))) {
    ApiRequest::error('Operazione non consentita.', 403);
}

// Colonna file dello schema: qui lo schema lazy viene calcolato.
$target = TableAuthorization::fileColumn($table, $column);

if ($target === null) {
    ApiRequest::error('Parametri non validi.', 422);
}

[$table, $column] = $target;

// Riga che l'utente non vede: come se non esistesse.
if (!RowVisibility::visible($table, $rowId, RowVisibility::currentUser())) {
    ApiRequest::error('Riga non trovata.', 404);
}

$query = sqlSelect($table, ['id' => $rowId], 1);

if (!($query->exists ?? false)) {
    ApiRequest::error('Riga non trovata.', 404);
}

$files = json_decode((string) ($query->row[$column] ?? '[]'), true);
$newId = $action === 'up' ? $oldId - 1 : $oldId + 1;

if (!is_array($files) || !array_key_exists($oldId, $files)) {
    ApiRequest::error('File non trovato.', 404);
}

if (!array_key_exists($newId, $files)) {
    ApiRequest::error('Posizione non valida.', 422);
}

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
