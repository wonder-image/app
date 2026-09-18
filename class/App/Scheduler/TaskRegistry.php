<?php

namespace Wonder\App\Scheduler;

use Wonder\App\Module\Contracts\ModuleTasks;
use Wonder\App\Module\Registry;
use Wonder\App\Scheduler\Contracts\TaskInterface;

final class TaskRegistry
{
    private static ?array $tasks = null;

    public static function all(): array
    {
        if (self::$tasks !== null) {
            return self::$tasks;
        }
        $tasks = [];
        $add = static function (iterable $items) use (&$tasks): void {
            $seen = [];
            foreach ($items as $task) {
                if (!$task instanceof TaskInterface || !preg_match('/^[a-z0-9][a-z0-9_.-]{0,119}$/D', $task->key())) {
                    throw new \InvalidArgumentException('Definizione attivita non valida.');
                }
                if (isset($seen[$task->key()])) {
                    throw new \LogicException('Attivita duplicata: '.$task->key());
                }
                new \Cron\CronExpression($task->expression());
                if ($task->enabled()) { $task->validate($task->defaultParameters()); }
                if ($task->timeout() < 1 || $task->timeout() > 3600) {
                    throw new \InvalidArgumentException('Timeout attivita non valido.');
                }
                $seen[$task->key()] = true;
                $tasks[$task->key()] = $task;
            }
        };
        $add([new Tasks\SitemapTask()]);
        if (is_callable(['App\\Models\\Site\\Euribor', 'sync'])) {
            $add([new Tasks\EuriborTask()]);
        }
        $moduleTasks = [];
        foreach (Registry::enabled() as $module) {
            $entrypoint = $module->entrypoint();
            if (is_subclass_of($entrypoint, ModuleTasks::class)) {
                foreach ($entrypoint::tasks() as $task) {
                    $moduleTasks[] = $task;
                }
            }
        }
        $add($moduleTasks);
        $file = rtrim((string) ($GLOBALS['ROOT'] ?? ''), '/').'/custom/config/tasks.php';
        if (is_file($file)) {
            $add(require $file);
        }
        return self::$tasks = $tasks;
    }

    public static function get(string $key): TaskInterface
    {
        return self::all()[$key] ?? throw new \InvalidArgumentException('Attivita non disponibile: '.$key);
    }

    public static function options(): array
    {
        return array_map(static fn (TaskInterface $task): string => $task->label(), self::all());
    }
}
