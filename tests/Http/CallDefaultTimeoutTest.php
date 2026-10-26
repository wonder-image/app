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

// Stesso costruttore con un default di 1 s: verifica che il default si applichi senza aspettarne 10.
final class ShortDefaultCall extends Call { public const DEFAULT_TIMEOUT = 1; }

check('il default è 10 secondi', fn () => Call::DEFAULT_TIMEOUT === 10);

check('il costruttore applica DEFAULT_TIMEOUT', fn () => str_contains(
    (string) file_get_contents(__DIR__ . '/../../class/Api/Call.php'),
    'curl_setopt($this->cURL, CURLOPT_TIMEOUT, static::DEFAULT_TIMEOUT);'
));

check('risposta sotto il default: la chiamata riesce', function () use ($port) {
    $result = (new Call("http://127.0.0.1:{$port}/?s=1", []))->method('GET')->call();

    return $result['success'] === true && $result['result'] === 'ok';
});

check('senza setter la chiamata si interrompe al default: errno 28', function () use ($port) {
    $start = microtime(true);
    $result = (new ShortDefaultCall("http://127.0.0.1:{$port}/?s=3", []))->method('GET')->call();

    return $result['success'] === false && $result['errno'] === 28 && microtime(true) - $start < 2.5;
});

check('timeout() sovrascrive il default', function () use ($port) {
    $result = (new ShortDefaultCall("http://127.0.0.1:{$port}/?s=2", []))->method('GET')->timeout(4)->call();

    return $result['success'] === true && $result['result'] === 'ok';
});

require_once __DIR__ . '/../../app/function/other/curl.php';

check('curlJson(): timeout opzionale in coda, alla scadenza null', function () use ($port) {
    $start = microtime(true);
    $result = curlJson("http://127.0.0.1:{$port}/?s=3", 'GET', [], null, null, null, 1);

    return $result === null && microtime(true) - $start < 2.5;
});

$root = __DIR__ . '/../..';
check('sorgente: QuickCreate chiama lo store con 60 s', fn () => str_contains(
    (string) file_get_contents($root . '/class/Backend/Support/QuickCreateController.php'),
    "curlJson(\$url, 'POST', \$values, \$token, null, null, 60)"
));
check('sorgente: PayPal (4 chiamate) e Nexi (2) impostano 30 s', fn () =>
    substr_count((string) file_get_contents($root . '/class/Plugin/PayPal/PayPal.php'), '$call->timeout(30);') === 4
    && substr_count((string) file_get_contents($root . '/class/Plugin/Nexi/Nexi.php'), '$call->timeout(30);') === 2);

// Con i worker, proc_terminate() chiude solo il padre: prima i figli
$serverPid = (int) (proc_get_status($server)['pid'] ?? 0);
if ($serverPid > 0) {
    exec('pkill -TERM -P ' . $serverPid);
}
proc_terminate($server);
proc_close($server);
@unlink($dir . '/router.php');
@rmdir($dir);
summary();
