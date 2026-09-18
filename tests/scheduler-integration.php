<?php

// Run only against a disposable site and a local test database.
$ROOT = $argv[1] ?? '';
if (!str_starts_with(basename($ROOT), 'wonder-scheduler-') || !is_file($ROOT.'/vendor/autoload.php')) {
    throw new RuntimeException('Provide a disposable wonder-scheduler-* site directory.');
}
$GLOBALS['ROOT'] = $ROOT;
require $ROOT.'/vendor/autoload.php';
\Wonder\Console\Commands\Build::writeScheduler($ROOT);
$config = $ROOT.'/custom/config/tasks.php';
if (is_file($config)) { throw new RuntimeException('Test tasks file already exists.'); }
file_put_contents($config, <<<'PHP'
<?php
use Wonder\App\Scheduler\{Task, Context};
return [
    Task::make('test.scheduler.ok', static function (Context $context): array {
        $context->log('hello');
        echo str_repeat('a', 30000);
        return ['processed' => 2];
    })->schedule('* * * * *'),
    Task::make('test.scheduler.fail', static function (): array { throw new RuntimeException('expected failure'); }),
    Task::make('test.scheduler.exit', static function (): array { exit(0); }),
    Task::make('test.scheduler.timeout', static function (): array { sleep(20); return []; })->maxSeconds(1),
];
PHP);
$FRONTEND = $BACKEND = false;
require dirname(__DIR__).'/wonder-image.php';

use Wonder\App\Scheduler\{Repository, Process, Scheduler};
use Wonder\App\Resources\Scheduler\{ScheduleResource, RunResource};

$repository = new Repository();
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
};
try {
    $repository->sync();
    $repository->sync();
    $check(count($repository->rows("SELECT id FROM scheduler_schedules WHERE task_key LIKE 'test.scheduler.%'")) === 4, 'Defaults duplicate');
    $repository->execute("UPDATE scheduler_schedules SET enabled = 'true', requested = 'true' WHERE task_key LIKE 'test.scheduler.%'");
    $output = '';
    $exit = Process::run([PHP_BINARY, $ROOT.'/bin/scheduler.php'], $ROOT, 30, static function ($chunk) use (&$output): void { $output .= $chunk; });
    $check($exit === 0, 'Scheduler process: '.$output);
    $runs = $repository->rows("SELECT * FROM scheduler_runs WHERE task_key LIKE 'test.scheduler.%' ORDER BY id");
    $check(count($runs) === 4, 'Expected four runs: '.$output);
    $byKey = array_column($runs, null, 'task_key');
    $check($byKey['test.scheduler.ok']['status'] === 'success', 'Successful task');
    $check((float) $byKey['test.scheduler.ok']['memory_bytes'] > 0 && (float) $byKey['test.scheduler.ok']['duration_ms'] >= 0, 'Measured memory and duration');
    $check(strlen($byKey['test.scheduler.ok']['output']) <= 16384, 'Bounded output');
    $check($byKey['test.scheduler.fail']['status'] === 'failed', 'Exceptions recorded');
    $check($byKey['test.scheduler.exit']['status'] === 'interrupted', 'Early exit recorded');
    $check($byKey['test.scheduler.timeout']['status'] === 'interrupted', 'Timeout recorded');
    $check(count($repository->statistics(180)) >= 4, 'Statistics');
    $repository->execute("UPDATE scheduler_schedules SET enabled = 'false', expression = '0 3 * * *' WHERE task_key = 'test.scheduler.ok'");
    $repository->sync();
    $row = $repository->rows("SELECT * FROM scheduler_schedules WHERE task_key = 'test.scheduler.ok'")[0];
    $check($row['enabled'] === 'false' && $row['expression'] === '0 3 * * *', 'Overrides preserved');
    try { $repository->request((int) $row['id']); $check(false, 'Disabled request accepted'); }
    catch (InvalidArgumentException) { $checks++; }
    $values = ScheduleResource::mutateRequestValues(['name' => 'Custom', 'task_key' => 'test.scheduler.ok', 'expression' => '*/5 * * * *', 'timezone' => 'Europe/Rome', 'parameters' => '{}', 'enabled' => 'true', 'last_started' => 'tampered'], 'store');
    $check(!isset($values['last_started']) && isset($values['next_due']), 'Server-owned values protected');
    $check(ScheduleResource::formLayoutSchema() !== null && RunResource::pageSchema() !== null, 'Backend schemas');
    $html = \Wonder\Backend\Support\ResourceFormLayoutRenderer::render(ScheduleResource::formLayoutSchema());
    $check(str_contains($html, 'scheduler_csrf') && str_contains($html, 'expression'), 'Backend form rendering');
    $busy = \Wonder\App\Support\NamedLock::run('scheduler:tick', static function () use ($ROOT): string {
        $output = '';
        Process::run([PHP_BINARY, $ROOT.'/bin/scheduler.php'], $ROOT, 10, static function ($chunk) use (&$output): void { $output .= $chunk; });
        return $output;
    });
    $check(str_contains($busy, '"busy":true'), 'Overlapping tick refused');
    $repository->execute("UPDATE scheduler_schedules SET deleted = 'true', enabled = 'true' WHERE id = ?", [$row['id']]);
    try { $repository->request((int) $row['id']); $check(false, 'Deleted request accepted'); }
    catch (InvalidArgumentException) { $checks++; }
    $repository->execute('DELETE FROM scheduler_schedules WHERE id = ?', [$row['id']]);
    $repository->sync();
    $check($repository->rows("SELECT id FROM scheduler_schedules WHERE task_key = 'test.scheduler.ok'") === [], 'Deleted default not recreated');
    $repository->execute("INSERT INTO scheduler_runs (task_key, status, started_at) VALUES ('test.scheduler.old', 'success', ?)", [gmdate('Y-m-d H:i:s', time() - 181 * 86400)]);
    $repository->execute("DELETE FROM scheduler_state WHERE state_key = 'cleanup'");
    $repository->cleanup();
    $check($repository->rows("SELECT id FROM scheduler_runs WHERE task_key = 'test.scheduler.old'") === [], '180-day retention');
    echo "$checks scheduler integration checks passed.\n";
} finally {
    $repository->execute("DELETE FROM scheduler_runs WHERE task_key LIKE 'test.scheduler.%'");
    $repository->execute("DELETE FROM scheduler_schedules WHERE task_key LIKE 'test.scheduler.%'");
    $repository->execute("DELETE FROM scheduler_state WHERE state_key LIKE 'default:test.scheduler.%'");
    unlink($config);
}
