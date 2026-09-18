<?php

namespace Wonder\App\Scheduler;

use Wonder\App\Support\NamedLock;

final class Worker
{
    public static function run(int $runId): int
    {
        if (PHP_SAPI !== 'cli') { throw new \RuntimeException('Solo CLI.'); }
        $repository = new Repository();
        $run = $repository->rows("SELECT * FROM scheduler_runs WHERE id = ? AND status = 'pending'", [$runId])[0] ?? null;
        if (!$run) { return 1; }
        $result = NamedLock::run('scheduler:task:'.$run['task_key'], static function () use ($repository, $run, $runId): int {
            $schedule = $repository->rows("SELECT * FROM scheduler_schedules WHERE id = ? AND deleted = 'false'", [$run['schedule_id']])[0] ?? null;
            if (!$schedule || $schedule['enabled'] !== 'true' || $schedule['task_key'] !== $run['task_key']) {
                $repository->execute("UPDATE scheduler_runs SET status = 'skipped', finished_at = ? WHERE id = ?", [gmdate('Y-m-d H:i:s'), $runId]);
                return 0;
            }
            $claimed = $repository->execute("UPDATE scheduler_runs SET status = 'running' WHERE id = ? AND status = 'pending'", [$runId]);
            if ($claimed->affected_rows !== 1) { return 1; }
            $start = hrtime(true);
            $context = new Context([], microtime(true) + 300);
            $finished = false;
            register_shutdown_function(static function () use (&$finished, &$context, $repository, $runId, $start): void {
                if (!$finished) {
                    $context->log("\nProcesso terminato prima del completamento (exit o errore fatale).");
                    $repository->execute("UPDATE scheduler_runs SET status = 'interrupted', finished_at = ?, duration_ms = ?, output = ? WHERE id = ? AND status = 'running'",
                        [gmdate('Y-m-d H:i:s'), (hrtime(true) - $start) / 1e6, $context->output(), $runId]);
                }
            });
            $cpu = self::cpu();
            $status = 'success';
            $data = [];
            $bufferLevel = ob_get_level();
            ob_start(static function (string $chunk) use (&$context): string { $context->log($chunk); return ''; }, 4096);
            try {
                $task = TaskRegistry::get($run['task_key']);
                $parameters = json_decode($schedule['parameters'] ?: '{}', true, 32, JSON_THROW_ON_ERROR);
                if (!is_array($parameters)) { throw new \InvalidArgumentException('Parametri JSON non validi.'); }
                $context = new Context($task->validate($parameters), microtime(true) + $task->timeout());
                $data = $task->run($context);
                $context->checkDeadline();
            } catch (\Throwable $error) {
                $status = 'failed';
                $context->log("\n".$error->getMessage());
            } finally {
                while (ob_get_level() > $bufferLevel) { ob_end_flush(); }
            }
            $encoded = $context->redact(json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}');
            if (strlen($encoded) > Context::OUTPUT_LIMIT) { $encoded = '{"truncated":true}'; }
            $cpuEnd = self::cpu();
            $repository->execute('UPDATE scheduler_runs SET status = ?, finished_at = ?, duration_ms = ?, memory_bytes = ?, cpu_ms = ?, output = ?, result = ? WHERE id = ? AND status = ?',
                [$status, gmdate('Y-m-d H:i:s'), (hrtime(true) - $start) / 1e6,
                    $context->externalProcess ? ($context->processMetrics['memory_bytes'] ?? null) : memory_get_peak_usage(true),
                    $context->externalProcess ? ($context->processMetrics['cpu_ms'] ?? null) : ($cpu === null || $cpuEnd === null ? null : $cpuEnd - $cpu),
                    $context->output(), $encoded, $runId, 'running']);
            $finished = true;
            return $status === 'success' ? 0 : 1;
        });
        if ($result === NamedLock::NOT_ACQUIRED) {
            $repository->execute("UPDATE scheduler_runs SET status = 'skipped', finished_at = ?, output = ? WHERE id = ? AND status = 'pending'",
                [gmdate('Y-m-d H:i:s'), 'Attivita gia in esecuzione.', $runId]);
            return 0;
        }
        return (int) $result;
    }

    private static function cpu(): ?float
    {
        if (!function_exists('getrusage')) { return null; }
        $usage = getrusage();
        return (($usage['ru_utime.tv_sec'] ?? 0) + ($usage['ru_stime.tv_sec'] ?? 0)) * 1000
            + (($usage['ru_utime.tv_usec'] ?? 0) + ($usage['ru_stime.tv_usec'] ?? 0)) / 1000;
    }
}
