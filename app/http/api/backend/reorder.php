<?php

use Wonder\App\LegacyGlobals;
use Wonder\App\ResourceRegistry;
use Wonder\App\Support\ApiRequest;
use Wonder\App\Support\SyncedTables;
use Wonder\App\Visibility\RowVisibility;
use Wonder\Backend\Table\Reorder;
use Wonder\Backend\Table\TableAuthorization;

// Il trascinamento di una riga nella lista di una Resource con
// `reorderable()`: `resource` lo slug, `id` la riga spostata, `page` gli id
// della pagina nell'ordine nuovo. Tabella e colonne le dice la Resource, mai
// la richiesta.

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$slug = ApiRequest::string('resource');
$rowId = ApiRequest::int('id');
$page = is_array($_POST['page'] ?? null) ? array_values(array_filter(array_map('intval', $_POST['page']), static fn (int $id): bool => $id > 0)) : [];

try {
    $resourceClass = ResourceRegistry::resolve($slug);
} catch (Throwable) {
    ApiRequest::error('Lista non trovata.', 404);
}

$table = $resourceClass::modelTable();

if ($rowId <= 0 || $page === [] || count($page) > 500 || Reorder::config($resourceClass::tableLayoutSchema()->get('reorder')) === null) {
    ApiRequest::error('Parametri non validi.', 422);
}

if ($resourceClass::isReadonly() || SyncedTables::isReadonly($table)) {
    ApiRequest::error('Tabella in sola lettura in questo ambiente.', 403);
}

// Permessi per tabella prima di qualsiasi query.
if (!TableAuthorization::make()->allows($table, TableAuthorization::UPDATE, TableAuthorization::authoritiesOf(LegacyGlobals::get('USER')))) {
    ApiRequest::error('Operazione non consentita.', 403);
}

$user = RowVisibility::currentUser();

// Riga che l'utente non vede: come se non esistesse.
if (!RowVisibility::visible($table, $rowId, $user)) {
    ApiRequest::error('Riga non trovata.', 404);
}

try {
    $changed = Reorder::apply($resourceClass, $rowId, $page, $user);
} catch (RuntimeException $error) {
    ApiRequest::error($error->getMessage(), 422);
}

SyncedTables::exportIfSynced($table);

ApiRequest::success('Ordine aggiornato.', [
    'id' => $rowId,
    'changed' => $changed,
]);
