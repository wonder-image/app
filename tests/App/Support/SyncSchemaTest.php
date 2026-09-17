<?php
/** php tests/App/Support/SyncSchemaTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\SyncSchema;

check('multiRow di default senza flag', function () {
    $schema = SyncSchema::multiRow();
    return $schema->singleton === false && $schema->keepIds === false && $schema->localOnly === false;
});

check('keepIds() e localOnly() impostano i flag', function () {
    $schema = SyncSchema::multiRow()->keepIds()->localOnly();
    return $schema->keepIds === true && $schema->localOnly === true && $schema->singleton === false;
});

check('le opzioni restituiscono nuove istanze', function () {
    $base = SyncSchema::multiRow();
    $kept = $base->keepIds();
    return $base !== $kept && $base->keepIds === false;
});

check('exclude() conserva i flag e viceversa', function () {
    $a = SyncSchema::multiRow()->keepIds()->localOnly()->exclude(['secret']);
    $b = SyncSchema::multiRow()->exclude(['secret'])->keepIds()->localOnly();
    return $a->keepIds && $a->localOnly && $a->excludeColumns === ['secret']
        && $b->keepIds && $b->localOnly && $b->excludeColumns === ['secret'];
});

check('singleton conserva singleton con le opzioni', function () {
    $schema = SyncSchema::singleton()->localOnly();
    return $schema->singleton === true && $schema->localOnly === true;
});

summary();
