<?php
/** php tests/Http/CallHttpCodeTest.php — il codice HTTP dell'ultima chiamata di Call. */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Api\Call;

echo "Call::httpCode\n";

$dir = sys_get_temp_dir() . '/wi-call-http-' . getmypid();
@mkdir($dir, 0777, true);
file_put_contents($dir . '/router.php', "<?php http_response_code(429); echo '{}';");

$probe = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
fclose($probe);

$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", $dir . '/router.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $dir);

for ($i = 0; $i < 50; $i++) {
    $socket = @fsockopen('127.0.0.1', $port);
    if ($socket) { fclose($socket); break; }
    usleep(100000);
}

check('prima della chiamata il codice è 0', fn () => (new Call("http://127.0.0.1:{$port}/", []))->httpCode() === 0);

check('dopo la chiamata il codice è quello del server (429)', function () use ($port) {
    $call = new Call("http://127.0.0.1:{$port}/", []);
    $call->call();

    return $call->httpCode() === 429;
});

check('server che non risponde: codice 0', function () {
    $call = new Call('http://127.0.0.1:1/', []);
    $call->connectTimeout(1)->timeout(1);
    $call->call();

    return $call->httpCode() === 0;
});

proc_terminate($server);
summary();
