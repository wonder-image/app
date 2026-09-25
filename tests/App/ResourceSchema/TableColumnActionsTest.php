<?php
/** php tests/App/ResourceSchema/TableColumnActionsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\ResourceSchema\TableColumn;
use Wonder\Backend\Support\ResourceTableRenderer;

$movimenti = ['label' => 'Movimenti', 'href' => '/backend/movimenti/?versione={id}'];

check('actions() con una lista accende le voci', function () {
    $actions = TableColumn::key('actions')->button()->actions(['edit', 'delete'])->toArray()['actions'];

    return $actions === ['edit' => true, 'delete' => true];
});

check('actions() con una mappa accende e spegne', function () {
    $actions = TableColumn::key('actions')->button()->actions(['edit' => true, 'delete' => false])->toArray()['actions'];

    return $actions === ['edit' => true];
});

check('una voce ad array arriva intera', function () use ($movimenti) {
    $actions = TableColumn::key('actions')->button()->actions(['edit', 'movimenti' => $movimenti])->toArray()['actions'];

    return $actions === ['edit' => true, 'movimenti' => $movimenti];
});

check('action() con un array vuoto spegne la voce', function () use ($movimenti) {
    $actions = TableColumn::key('actions')->button()
        ->action('movimenti', $movimenti)
        ->action('movimenti', [])
        ->toArray()['actions'];

    return $actions === [];
});

check('resolveActions: edit diventa modify, le voci ad array passano', function () use ($movimenti) {
    $resolved = ResourceTableRenderer::resolveActions(['edit' => true, 'view' => true, 'movimenti' => $movimenti], false);

    return $resolved === ['modify' => true, 'view' => true, 'movimenti' => $movimenti];
});

check('resolveActions: in sola lettura niente delete e duplicate, neanche ad array', function () {
    $resolved = ResourceTableRenderer::resolveActions([
        'view' => true,
        'delete' => ['label' => 'Elimina'],
        'duplicate' => true,
    ], true);

    return $resolved === ['view' => true];
});

check('resolveActions: voci spente o vuote saltate', function () {
    return ResourceTableRenderer::resolveActions(['edit' => false, 'movimenti' => []], false) === [];
});

summary();
