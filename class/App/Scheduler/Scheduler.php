<?php

namespace Wonder\App\Scheduler;

use Wonder\App\Support\NamedLock;

final class Scheduler
{
    public static function tick(): array
    {
        if (PHP_SAPI !== 'cli') { throw new \RuntimeException('Lo scheduler si avvia solo da CLI.'); }
        if (!function_exists('proc_open')) { throw new \RuntimeException('proc_open non disponibile sul server.'); }
        $user = infoUser('@system', 'username');
        if (!($user->exists ?? false) || ($user->api_internal_user->active ?? '') !== 'true') {
            throw new \RuntimeException('Utente API @system assente o disabilitato.');
        }
        $result = NamedLock::run('scheduler:tick', static function (): array {
            $repository = new Repository();
            $repository->sync();
            $repository->setState('heartbeat', gmdate('Y-m-d H:i:s'));
            // A worker still alive keeps its task lock even if the previous supervisor died.
            foreach ($repository->rows("SELECT DISTINCT task_key FROM scheduler_runs WHERE status IN ('pending', 'running') AND started_at < ?", [gmdate('Y-m-d H:i:s', time() - 3700)]) as $stale) {
                NamedLock::run('scheduler:task:'.$stale['task_key'], static function () use ($repository, $stale): void {
                    $repository->execute("UPDATE scheduler_runs SET status = 'interrupted', finished_at = ?, output = ? WHERE task_key = ? AND status IN ('pending', 'running') AND started_at < ?",
                        [gmdate('Y-m-d H:i:s'), 'Worker interrotto; metriche finali non disponibili.', $stale['task_key'], gmdate('Y-m-d H:i:s', time() - 3700)]);
                });
            }
            $repository->cleanup();
            $deadline = microtime(true) + 50;
            $count = 0;
            $rows = $repository->rows("SELECT * FROM scheduler_schedules WHERE deleted = 'false' AND enabled = 'true' AND (requested = 'true' OR next_due <= ?) ORDER BY COALESCE(last_started, '1970-01-01'), id LIMIT 100", [gmdate('Y-m-d H:i:s')]);
            foreach ($rows as $row) {
                if (microtime(true) >= $deadline) { break; }
                if (!isset(TaskRegistry::all()[$row['task_key']])) { continue; }
                $task = TaskRegistry::get($row['task_key']);
                $next = Repository::next($row['expression'], $row['timezone']);
                $repository->execute("UPDATE scheduler_schedules SET requested = 'false', next_due = ?, last_started = ? WHERE id = ?", [$next, gmdate('Y-m-d H:i:s'), $row['id']]);
                $repository->execute("INSERT INTO scheduler_runs (schedule_id, task_key, status, started_at) VALUES (?, ?, 'pending', ?)", [$row['id'], $row['task_key'], gmdate('Y-m-d H:i:s')]);
                $id = (int) \Wonder\Sql\Connection::Connect('main')->insert_id;
                $context = new Context([], microtime(true) + $task->timeout() + 10);
                try {
                    Process::run([PHP_BINARY, rtrim($GLOBALS['ROOT'], '/').'/bin/scheduler.php', '--worker='.$id], $GLOBALS['ROOT'], $task->timeout() + 10, $context->log(...));
                } catch (\Throwable $error) {
                    $context->log($error->getMessage());
                }
                $repository->execute("UPDATE scheduler_runs SET status = 'interrupted', finished_at = ?, output = ? WHERE id = ? AND status IN ('pending', 'running')",
                    [gmdate('Y-m-d H:i:s'), $context->output() ?: 'Worker terminato senza risultato.', $id]);
                $count++;
            }
            return ['executed' => $count];
        });
        return $result === NamedLock::NOT_ACQUIRED ? ['busy' => true] : $result;
    }

}
