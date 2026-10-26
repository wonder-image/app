<?php

use Wonder\App\LegacyGlobals;
use Wonder\App\Support\ApiRequest;
use Wonder\Backend\Table\ListTableRequest;
use Wonder\Backend\Table\TableAuthorization;

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$table = ApiRequest::string('table');

if ($table !== '' && \Wonder\App\Support\SyncedTables::isReadonly($table)) {
    ApiRequest::error('Tabella in sola lettura in questo ambiente.', 403);
}

$id = ApiRequest::int('id');

// Tabella registrata (senza calcolarne lo schema), cartella sotto upload. La
// cartella arriva dalla richiesta: lo schema in Table non la dichiara (da decidere).
$table = ListTableRequest::table($table);
$folder = TableAuthorization::uploadFolder(ApiRequest::string('folder'));

if ($table === null || $folder === null || $folder === '' || $id <= 0) {
    ApiRequest::error('Parametri non validi.', 422);
}

// Permessi per tabella prima di qualsiasi query (Resource o solo admin).
if (!TableAuthorization::make()->allows($table, TableAuthorization::UPDATE, TableAuthorization::authoritiesOf(LegacyGlobals::get('USER')))) {
    ApiRequest::error('Operazione non consentita.', 403);
}

// Colonna icon nello schema: qui lo schema lazy viene calcolato.
if (TableAuthorization::iconTable($table) === null) {
    ApiRequest::error('Parametri non validi.', 422);
}

$query = sqlSelect($table, ['id' => $id], 1);

if (!($query->exists ?? false)) {
    ApiRequest::error('Riga non trovata.', 404);
}

$icon = trim((string) ($query->row['icon'] ?? ''));

if ($icon !== '') {
    $path = TableAuthorization::uploadFile((string) $PATH->rUpload, $folder, $icon);

    if ($path !== null) {
        @unlink($path);
    }
}

sqlModify($table, ['icon' => ''], 'id', $id);

ApiRequest::success('Icona eliminata.', [
    'table' => $table,
    'id' => $id,
]);
