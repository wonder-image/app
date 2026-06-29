<?php

use Wonder\App\Support\ApiRequest;
use Wonder\App\Support\MediaFileManager;

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$folder = ApiRequest::string('folder');
$table = ApiRequest::string('table');
$column = ApiRequest::string('column');
$rowId = ApiRequest::int('row_id');
$fileId = ApiRequest::int('file_id', -1);

if ($table === '' || $column === '' || $rowId <= 0 || $fileId < 0) {
    ApiRequest::error('Parametri non validi.', 422);
}

$query = sqlSelect($table, ['id' => $rowId], 1);

if (!($query->exists ?? false)) {
    ApiRequest::error('Riga non trovata.', 404);
}

$files = json_decode((string) ($query->row[$column] ?? '[]'), true);
$files = is_array($files) ? $files : [];

$newArray = [];

foreach ($files as $id => $image) {
    if ((int) $id !== $fileId) {
        $newArray[] = $image;
        continue;
    }

    $tableKey = strtoupper($table);
    $tableColumn = $TABLE->$tableKey[$column]['input'] ?? [];
    $format = (array) ($tableColumn['format'] ?? []);
    $basePath = rtrim((string) $PATH->rUpload, '/');
    $trimmedFolder = trim($folder, '/');

    if ($trimmedFolder !== '') {
        $basePath .= '/'.$trimmedFolder;
    }

    MediaFileManager::deleteFile($basePath, $format, (string) $image);
}

sqlModify($table, [$column => json_encode(array_values($newArray))], 'id', $rowId);

ApiRequest::success('File eliminato.', [
    'table' => $table,
    'column' => $column,
    'id' => $rowId,
]);
