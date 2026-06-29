<?php

use Wonder\App\Support\ApiRequest;

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$table = ApiRequest::string('table');
$folder = ApiRequest::string('folder');
$id = ApiRequest::int('id');

if ($table === '' || $folder === '' || $id <= 0) {
    ApiRequest::error('Parametri non validi.', 422);
}

$query = sqlSelect($table, ['id' => $id], 1);

if (!($query->exists ?? false)) {
    ApiRequest::error('Riga non trovata.', 404);
}

$icon = trim((string) ($query->row['icon'] ?? ''));

if ($icon !== '') {
    $path = rtrim((string) $PATH->rUpload, '/').'/'.trim($folder, '/').'/'.$icon;

    if (is_file($path)) {
        @unlink($path);
    }
}

sqlModify($table, ['icon' => ''], 'id', $id);

ApiRequest::success('Icona eliminata.', [
    'table' => $table,
    'id' => $id,
]);
