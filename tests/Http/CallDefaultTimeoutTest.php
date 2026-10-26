<?php
/** php tests/Http/CallDefaultTimeoutTest.php — timeout di default di Call, sovrascrivibile. */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Api\Call;

echo "Call: timeout di default\n";

$dir = sys_get_temp_dir() . '/wi-call-timeout-' . getmypid();
@mkdir($dir, 0777, true);
// Risponde dopo i secondi chiesti in ?s=
file_put_contents($dir . '/router.php', "<?php sleep((int) (\$_GET['s'] ?? 0)); echo 'ok';");

$probe = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
fclose($probe);

$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", $dir . '/router.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $dir, ['PHP_CLI_SERVER_WORKERS' => '4']);

for ($i = 0; $i < 50; $i++) {
    $socket = @fsockopen('127.0.0.1', $port);
    if ($socket) { fclose($socket); break; }
    usleep(100000);
}

check('il default è 10 secondi', fn () => Call::DEFAULT_TIMEOUT === 10);

check('risposta sotto il default: la chiamata riesce', function () use ($port) {
    $result = (new Call("http://127.0.0.1:{$port}/?s=2", []))->method('GET')->call();

    return $result['success'] === true && $result['result'] === 'ok';
});

check('timeout() più corto sovrascrive il default: errno 28', function () use ($port) {
    $start = microtime(true);
    $result = (new Call("http://127.0.0.1:{$port}/?s=3", []))->method('GET')->timeout(1)->call();

    return $result['success'] === false && $result['errno'] === 28 && microtime(true) - $start < 2.5;
});

check('senza setter la chiamata si interrompe a 10 secondi', function () use ($port) {
    $start = microtime(true);
    $result = (new Call("http://127.0.0.1:{$port}/?s=13", []))->method('GET')->call();
    $elapsed = microtime(true) - $start;

    return $result['success'] === false && $result['errno'] === 28 && $elapsed >= 9.5 && $elapsed < 12;
});

proc_terminate($server);
@unlink($dir . '/router.php');
@rmdir($dir);
summary();
