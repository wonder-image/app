<?php

namespace Wonder\App\Scheduler;

final class Process
{
    /** Array commands bypass shell parsing. Output is always drained, but retained only up to the cap. */
    public static function run(array $command, string $cwd, int $timeout, callable $output): int
    {
        if (!function_exists('proc_open')) { throw new \RuntimeException('proc_open non disponibile: lo scheduler richiede processi CLI.'); }
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
        if (!is_resource($process)) { throw new \RuntimeException('Impossibile avviare PHP CLI.'); }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $deadline = microtime(true) + $timeout;
        $timedOut = false;
        try {
            do {
                foreach ([1, 2] as $pipe) {
                    $chunk = stream_get_contents($pipes[$pipe], 8192);
                    if (is_string($chunk) && $chunk !== '') { $output($chunk); }
                }
                $status = proc_get_status($process);
                if (!$status['running']) { break; }
                if (microtime(true) >= $deadline) {
                    $timedOut = true;
                    proc_terminate($process, 9);
                    break;
                }
                usleep(20000);
            } while (true);
            foreach ([1, 2] as $pipe) {
                while (($chunk = fread($pipes[$pipe], 8192)) !== false && $chunk !== '') { $output($chunk); }
            }
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            $closed = proc_close($process);
        }
        if ($timedOut) { throw new \RuntimeException('Timeout del processo.'); }
        return (int) (($status['exitcode'] ?? -1) >= 0 ? $status['exitcode'] : $closed);
    }
}
