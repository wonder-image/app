<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$ROOT = $argv[1] ?? '';
if (!is_file($ROOT.'/vendor/autoload.php')) { exit(1); }
$GLOBALS['ROOT'] = $ROOT;
$metrics = $argv[2] ?? '';
if (!is_file($metrics) || realpath(dirname($metrics)) !== realpath($ROOT.'/storage/tmp')) { exit(1); }
register_shutdown_function(static function () use ($metrics): void {
    $cpu = null;
    if (function_exists('getrusage')) {
        $usage = getrusage();
        $cpu = (($usage['ru_utime.tv_sec'] ?? 0) + ($usage['ru_stime.tv_sec'] ?? 0)) * 1000
            + (($usage['ru_utime.tv_usec'] ?? 0) + ($usage['ru_stime.tv_usec'] ?? 0)) / 1000;
    }
    file_put_contents($metrics, json_encode(['memory_bytes' => memory_get_peak_usage(true), 'cpu_ms' => $cpu]));
});
$FRONTEND = $BACKEND = false;
chdir($ROOT);
require dirname(__DIR__).'/wonder-image.php';
$result = \Wonder\App\Support\NamedLock::run('scheduler:crawler:sitemap', static function (): void {
    extract(\Wonder\App\LegacyGlobals::scope());
    require dirname(__DIR__).'/vendor-static/xml-sitemaps/runcrawl.php';
});
if ($result === \Wonder\App\Support\NamedLock::NOT_ACQUIRED) { exit(75); }
