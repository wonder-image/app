<?php
/** php tests/App/Support/SyncImportPlanTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\SyncImportPlan;

check('righe nuove da inserire con il proprio id', function () {
    $plan = SyncImportPlan::make([['id' => 3, 'code' => 'cash', 'deleted' => 'false']], []);
    return $plan->inserts === [['id' => 3, 'code' => 'cash', 'deleted' => 'false']]
        && $plan->updates === [] && $plan->softDeletes === [] && $plan->skipped === 0;
});

check('righe esistenti da aggiornare senza la colonna id', function () {
    $plan = SyncImportPlan::make([['id' => 1, 'name' => 'Bonifico']], [1]);
    return $plan->inserts === [] && $plan->updates === [1 => ['name' => 'Bonifico']];
});

check('righe assenti dal file da segnare come cancellate', function () {
    $plan = SyncImportPlan::make([['id' => 1, 'name' => 'A']], [1, 2, '5']);
    return $plan->softDeletes === [2, 5];
});

check('righe cancellate nel file restano aggiornate con il loro deleted', function () {
    $plan = SyncImportPlan::make([['id' => 2, 'deleted' => 'true']], [2]);
    return $plan->updates === [2 => ['deleted' => 'true']] && $plan->softDeletes === [];
});

check('file vuoto: tutte le righe esistenti segnate come cancellate', function () {
    $plan = SyncImportPlan::make([], [1, 2]);
    return $plan->inserts === [] && $plan->updates === [] && $plan->softDeletes === [1, 2];
});

check('righe senza id valido o duplicate saltate', function () {
    $plan = SyncImportPlan::make([
        ['name' => 'senza id'],
        ['id' => 0, 'name' => 'zero'],
        ['id' => 4, 'name' => 'prima'],
        ['id' => 4, 'name' => 'duplicato'],
        'non array',
    ], []);
    return $plan->skipped === 4 && $plan->inserts === [['id' => 4, 'name' => 'prima']];
});

summary();
