<?php
/**
 * php tests/App/Support/SyncPlanTest.php
 *
 * Prova a secco della sincronizzazione: rifiuta fuori da staging e mette le
 * tabelle nelle tre classi senza copiare niente.
 */
declare(strict_types=1);

namespace Wonder\Tests\SyncPlan;

use Wonder\App\Environment;
use Wonder\App\Support\SyncDump;
use Wonder\App\Support\SyncPlan;
use Wonder\Console\Commands\SyncFromDump;

require __DIR__ . '/../../harness.php';
require __DIR__ . '/../../../vendor/autoload.php';

$setEnv = static function (string $value): void {
    $_ENV['APP_ENV'] = $value;
    $_SERVER['APP_ENV'] = $value;
    putenv('APP_ENV='.$value);
    Environment::reset();
};

check('rifiuta con APP_ENV local e production', function () use ($setEnv) {
    foreach (['local', 'production'] as $env) {
        $setEnv($env);
        if (SyncPlan::refusal() === null) {
            return false;
        }
    }

    return true;
});

check('accetta con APP_ENV staging', function () use ($setEnv) {
    $setEnv('staging');

    return SyncPlan::refusal() === null;
});

check('il piano del framework non ha problemi bloccanti e divide le tabelle', function () {
    $plan = SyncPlan::build();

    return $plan['blocking'] === []
        && count($plan['copy']) === 23
        && count($plan['skipCode']) === 8
        && count($plan['skipEnvironment']) === 27
        && in_array('security', $plan['skipEnvironment'], true)
        && !in_array('security', $plan['copy'], true)
        && in_array('user', $plan['copy'], true)
        && in_array('scheduler_schedules', $plan['copy'], true)
        && in_array('society', $plan['copy'], true);
});

check('il comando è registrato come sync:from-dump con --dump e --dry-run', function () {
    $c = new SyncFromDump();

    return $c->getName() === 'sync:from-dump' && $c->getDefinition()->hasOption('dry-run') && $c->getDefinition()->hasOption('dump');
});

check('Forge registra il comando', function () {
    return str_contains((string) file_get_contents(__DIR__.'/../../../class/Console/Forge.php'), 'SyncFromDump::class');
});

$dumpFile = sys_get_temp_dir().'/zz_sync_dump_'.getmypid().'.sql';
file_put_contents($dumpFile, "-- dump\nCREATE TABLE `society` (\n  `id` int\n);\nINSERT INTO `society` VALUES (1);\nCREATE TABLE `zz_tabella_sconosciuta` (\n  `id` int\n);\nINSERT INTO `security` VALUES (1);\n");

check('SyncDump legge solo i nomi delle tabelle dal dump', fn () =>
    SyncDump::tables($dumpFile) === ['security', 'society', 'zz_tabella_sconosciuta']);

check('una tabella del dump non classificata blocca; le content assenti dal dump sono segnalate', function () use ($dumpFile) {
    $plan = SyncPlan::build(SyncDump::tables($dumpFile));

    return $plan['unknownInDump'] === ['zz_tabella_sconosciuta']
        && in_array('zz_tabella_sconosciuta è nel dump ma non è classificata', $plan['blocking'], true)
        && in_array('user', $plan['missingFromDump'], true)
        && !in_array('society', $plan['missingFromDump'], true);
});

check('un dump che non esiste fa fallire la lettura', function () {
    try {
        SyncDump::tables('/nonexistent/zz.sql');
    } catch (\RuntimeException $e) {
        return true;
    }

    return false;
});

@unlink($dumpFile);

summary();
