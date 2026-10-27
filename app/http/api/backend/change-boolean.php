<?php

use Wonder\App\LegacyGlobals;
use Wonder\App\Support\ApiRequest;
use Wonder\App\Visibility\RowVisibility;
use Wonder\Backend\Table\TableAuthorization;

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$routeMeta = is_array($ROUTE_META ?? null) ? $ROUTE_META : [];
$table = ApiRequest::string('table');

if ($table !== '' && \Wonder\App\Support\SyncedTables::isReadonly($table)) {
    ApiRequest::error('Tabella in sola lettura in questo ambiente.', 403);
}

$column = trim((string) (($routeMeta['legacy_column'] ?? null) ?: ApiRequest::string('column')));
$id = ApiRequest::int('id');

if ($table === '' || $column === '' || $id <= 0) {
    ApiRequest::error('Parametri non validi.', 422);
}

// Permessi per tabella prima di qualsiasi query (Resource o solo admin).
if (!TableAuthorization::make()->allows($table, TableAuthorization::UPDATE, TableAuthorization::authoritiesOf(LegacyGlobals::get('USER')))) {
    ApiRequest::error('Operazione non consentita.', 403);
}

ApiRequest::selectDatabase(ApiRequest::string('database', 'main'));

// Riga che l'utente non vede: come se non esistesse.
if (!RowVisibility::visible($table, $id, RowVisibility::currentUser())) {
    ApiRequest::error('Riga non trovata.', 404);
}

$query = sqlSelect($table, ['id' => $id], 1);
$row = is_array($query->row ?? null) ? $query->row : [];

if ($row === []) {
    ApiRequest::error('Riga non trovata.', 404);
}

$bool = (($row[$column] ?? 'false') === 'true') ? 'false' : 'true';
sqlModify($table, [$column => $bool], 'id', $id);

\Wonder\App\Support\SyncedTables::exportIfSynced($table);

ApiRequest::success('Stato aggiornato.', [
    'value' => $bool,
    'table' => $table,
    'column' => $column,
    'id' => $id,
]);
