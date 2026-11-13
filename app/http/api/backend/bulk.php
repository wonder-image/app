<?php

use Wonder\App\LegacyGlobals;
use Wonder\App\ResourceRegistry;
use Wonder\App\Support\ApiRequest;
use Wonder\App\Support\SyncedTables;
use Wonder\App\Visibility\RowVisibility;
use Wonder\Backend\Table\BulkActions;
use Wonder\Backend\Table\TableAuthorization;
use Wonder\Http\Csrf;

// Un'azione di massa dalla lista di una Resource con `bulkActions()`:
// `resource` lo slug, `action` la chiave, `ids` le righe scelte. Tabella,
// gestore e permesso li dice la Resource, mai la richiesta.

if (!ApiRequest::isPost()) {
    ApiRequest::error(__t('bulk.errors.method'), 405);
}

if (!Csrf::verify()) {
    ApiRequest::error(__t('bulk.errors.csrf'), 419);
}

$slug = ApiRequest::string('resource');
$key = ApiRequest::string('action');
$ids = BulkActions::ids($_POST['ids'] ?? null);

try {
    $resourceClass = ResourceRegistry::resolve($slug);
} catch (Throwable) {
    ApiRequest::error(__t('bulk.errors.not_found'), 404);
}

$actions = BulkActions::normalize($resourceClass::tableLayoutSchema()->get('bulk_actions'));
$action = $actions[$key] ?? null;

if ($action === null || $ids === null || $ids === []) {
    ApiRequest::error(__t('bulk.errors.invalid'), 422);
}

$table = $resourceClass::modelTable();

if ($resourceClass::isReadonly() || SyncedTables::isReadonly($table)) {
    ApiRequest::error(__t('bulk.errors.readonly'), 403);
}

// Permesso della Resource (o di quella indicata dall'azione) prima di qualsiasi query.
if (!BulkActions::allowed($action, $resourceClass, TableAuthorization::authoritiesOf(LegacyGlobals::get('USER')))) {
    ApiRequest::error(__t('bulk.errors.forbidden'), 403);
}

$user = RowVisibility::currentUser();

// Solo le righe che la lista mostrerebbe a questo utente.
$condition = $resourceClass::querySchema()['condition'] ?? ['deleted' => 'false'];
$ids = BulkActions::visibleIds($table, $ids, $condition, $user);

if ($ids === []) {
    ApiRequest::error(__t('bulk.errors.not_found'), 404);
}

$result = BulkActions::run($action, $resourceClass, $ids, $user);

if ($result['done'] !== []) {
    SyncedTables::exportIfSynced($table);
}

ApiRequest::success(__t('bulk.done', ['count' => (string) count($result['done'])]), BulkActions::response($result));
