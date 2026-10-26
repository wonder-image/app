<?php

use Wonder\App\LegacyGlobals;
use Wonder\App\Support\ApiRequest;
use Wonder\App\Support\MediaFileManager;
use Wonder\Backend\Table\TableAuthorization;

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$folder = ApiRequest::string('folder');
$table = ApiRequest::string('table');

if ($table !== '' && \Wonder\App\Support\SyncedTables::isReadonly($table)) {
    ApiRequest::error('Tabella in sola lettura in questo ambiente.', 403);
}

$column = ApiRequest::string('column');
$rowId = ApiRequest::int('row_id');
$fileId = ApiRequest::int('file_id', -1);

// Tabella registrata, colonna file dello schema, cartella sotto upload.
$target = TableAuthorization::fileColumn($table, $column);
$folder = TableAuthorization::uploadFolder($folder);

if ($target === null || $folder === null || $rowId <= 0 || $fileId < 0) {
    ApiRequest::error('Parametri non validi.', 422);
}

[$table, $column] = $target;

// Permessi per tabella prima di qualsiasi query (Resource o solo admin).
if (!TableAuthorization::make()->allows($table, TableAuthorization::UPDATE, TableAuthorization::authoritiesOf(LegacyGlobals::get('USER')))) {
    ApiRequest::error('Operazione non consentita.', 403);
}

$query = sqlSelect($table, ['id' => $rowId], 1);

if (!($query->exists ?? false)) {
    ApiRequest::error('Riga non trovata.', 404);
}

$files = json_decode((string) ($query->row[$column] ?? '[]'), true);

// Niente riscrittura se la colonna non contiene quel file.
if (!is_array($files) || !array_key_exists($fileId, $files)) {
    ApiRequest::error('File non trovato.', 404);
}

$newArray = [];

foreach ($files as $id => $image) {
    if ((int) $id !== $fileId) {
        $newArray[] = $image;
        continue;
    }

    $format = (array) (\Wonder\App\Table::key($table)->schema()[$column]['input']['format'] ?? []);
    $basePath = rtrim((string) $PATH->rUpload, '/');

    if ($folder !== '') {
        $basePath .= '/'.$folder;
    }

    MediaFileManager::deleteFile($basePath, $format, (string) $image);
}

sqlModify($table, [$column => json_encode(array_values($newArray))], 'id', $rowId);

ApiRequest::success('File eliminato.', [
    'table' => $table,
    'column' => $column,
    'id' => $rowId,
]);
