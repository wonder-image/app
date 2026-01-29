<?php

    namespace Wonder\App;

    use DateTimeImmutable;
    use DateTimeInterface;
    use Throwable;

    class Logger {

        public static function log( Throwable $exception, string $service, string $action, string $level = 'ERROR', string $file = 'error', array $context = [] ): void
        {

            $appDebug = !empty($_ENV['APP_DEBUG']);

            $caller = self::resolveCaller();
            $callerFile = $caller['file'] ?? '-';
            $callerLine = $caller['line'] ?? 0;
            $callerPath = $callerFile !== '-' ? str_replace(ROOT, '', $callerFile) : '-';

            $ts = (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM);

            $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? ($_SERVER['REQUEST_ID'] ?? '-');
            $traceId = $_SERVER['HTTP_TRACE_ID'] ?? ($_SERVER['TRACE_ID'] ?? '-');

            $base = [
                'ts'      => $ts,
                'level'   => $level,
                'service' => $service,
                'op'      => $action,
                'msg'     => $exception->getMessage(),
                'code'    => (string)$exception->getCode(),
                'ex'      => get_class($exception),
                'file'    => basename((string)$callerPath),
                'path'    => (string)$callerPath,
                'line'    => (string)$callerLine,
                'request_id' => (string)$requestId,
                'trace_id'   => (string)$traceId,
                'app_debug'  => $appDebug ? '1' : '0',
            ];

            $line = self::formatKv($base, $context);

            $logFile = rtrim(ROOT, "/")."/".ltrim($file, "/").".log";
            error_log($line."\n", 3, $logFile);

            if ($appDebug) {

                self::renderDebug($service, $action, $callerPath, $callerLine, $exception);

            }

        }

        private static function formatKv( array $base, array $context = [] ): string
        {

            if (!empty($context)) {

                foreach ($context as $k => $v) {

                    if (!is_string($k) || $k === '') {
                        continue;
                    }

                    if (array_key_exists($k, $base)) {
                        continue;
                    }

                    if (is_null($v)) {
                        $base[$k] = '-';
                    } elseif (is_bool($v)) {
                        $base[$k] = $v ? '1' : '0';
                    } elseif (is_scalar($v)) {
                        $base[$k] = (string)$v;
                    } else {
                        $base[$k] = json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        if ($base[$k] === false) $base[$k] = '[unserializable]';
                    }

                    if (is_string($base[$k]) && strlen($base[$k]) > 2000) {
                        $base[$k] = substr($base[$k], 0, 2000) . "\u{2026}";
                    }

                }

            }

            $parts = [];
            foreach ($base as $k => $v) {

                $k = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', (string)$k);
                $v = (string)$v;

                if ($v === '' || preg_match('/\s|["=]/', $v)) {
                    $v = '"' . addcslashes($v, "\\\"\n\r\t") . '"';
                }
                $parts[] = "{$k}={$v}";

            }
            return implode(' ', $parts);

        }

        private static function resolveCaller(): array
        {

            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);

            foreach ($bt as $frame) {

                if (($frame['class'] ?? '') === __CLASS__) {
                    continue;
                }

                if (($frame['function'] ?? '') === '__log') {
                    continue;
                }

                if (!isset($frame['file'])) {
                    continue;
                }

                return $frame;

            }

            return $bt[0] ?? [];

        }

        private static function renderDebug( string $service, string $action, string $callerPath, int $callerLine, Throwable $exception ): void
        {

            echo "[$service] $action file <b>".$callerPath."</b> line <b>".$callerLine."</b><br>";
            echo $exception->getMessage();
            exit();

        }

    }
