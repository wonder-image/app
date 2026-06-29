<?php

use Wonder\App\Support\ApiRequest;

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$table = ApiRequest::string('table');
$id = ApiRequest::int('id');

if ($table === '' || $id <= 0) {
    ApiRequest::error('Parametri non validi.', 422);
}

ApiRequest::selectDatabase(ApiRequest::string('database', 'main'));

$area = ApiRequest::string('area');
$authority = ApiRequest::string('authority');
$sql = sqlSelect($table, ['id' => $id], 1);

if (!($sql->exists ?? false)) {
    ApiRequest::error('Riga non trovata.', 404);
}

$userArea = json_decode((string) ($sql->row['area'] ?? '[]'), true);
$userAuthority = json_decode((string) ($sql->row['authority'] ?? '[]'), true);

$userArea = is_array($userArea) ? array_values($userArea) : [];
$userAuthority = is_array($userAuthority) ? array_values($userAuthority) : [];

$values = [];

if ($area !== '') {
    $values['area'] = json_encode(
        array_values(array_filter($userArea, static fn (mixed $value): bool => $value !== $area))
    );
}

if ($authority !== '') {
    $values['authority'] = json_encode(
        array_values(array_filter($userAuthority, static fn (mixed $value): bool => $value !== $authority))
    );
}

if ($area === 'backend' && $authority === '') {
    $values['authority'] = json_encode(array_values(array_filter(
        $userAuthority,
        static fn (mixed $value): bool => permissions($value)->area != 'backend'
    )));
}

if ($values !== []) {
    sqlModify($table, $values, 'id', $id);
}

ApiRequest::success('Autorizzazioni aggiornate.', [
    'table' => $table,
    'id' => $id,
]);
