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

$rowId = ApiRequest::int('id');
$action = ApiRequest::string('action');

if ($table === '' || $rowId <= 0 || !in_array($action, ['up', 'down'], true)) {
    ApiRequest::error('Parametri non validi.', 422);
}

// Permessi per tabella prima di qualsiasi query (Resource o solo admin).
if (!TableAuthorization::make()->allows($table, TableAuthorization::UPDATE, TableAuthorization::authoritiesOf(LegacyGlobals::get('USER')))) {
    ApiRequest::error('Operazione non consentita.', 403);
}

ApiRequest::selectDatabase(ApiRequest::string('database', 'main'));

// Riga che l'utente non vede: come se non esistesse.
if (!RowVisibility::visible($table, $rowId, RowVisibility::currentUser())) {
    ApiRequest::error('Riga non trovata.', 404);
}

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

// Lo scambio non deve spostare una riga che l'utente non vede.
if (RowVisibility::conditionFor($table, RowVisibility::currentUser()) !== null) {
    $neighbourCondition = ['position' => $newPosition, 'deleted' => 'false'];

    if ($filter !== null && $filter !== '' && $filterId !== '') {
        $neighbourCondition[$filter] = $filterId;
    }

    $neighbourId = (int) (sqlSelect($table, $neighbourCondition, 1)->id ?? 0);

    if ($neighbourId > 0 && !RowVisibility::visible($table, $neighbourId, RowVisibility::currentUser())) {
        ApiRequest::error('Posizione non valida.', 422);
    }
}

if ($filter !== null && $filter !== '' && $filterId !== '') {
    $oldPositionId = sqlSelect($table, ['position' => $newPosition, $filter => $filterId, 'deleted' => 'false'], 1)->id;

    if (!empty($oldPositionId)) {
        sqlModify($table, ['position' => $oldPosition, 'deleted' => 'false'], 'id', $oldPositionId);
    }
} else {
    sqlModify($table, ['position' => $oldPosition, 'deleted' => 'false'], 'position', $newPosition);
}

sqlModify($table, ['position' => $newPosition, 'deleted' => 'false'], 'id', $rowId);

\Wonder\App\Support\SyncedTables::exportIfSynced($table);

ApiRequest::success('Posizione aggiornata.', [
    'table' => $table,
    'id' => $rowId,
    'position' => $newPosition,
]);
