<?php
/** php tests/Http/ApiErrorMessageTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Api\Handler;
use Wonder\App\Debug;
use Wonder\App\Environment;
use Wonder\Http\HttpErrorResponse;
use Wonder\Http\RequestId;

function debugMode(bool $on): void {
    foreach (['APP_DEBUG', 'APP_ENV'] as $k) { unset($_ENV[$k], $_SERVER[$k]); putenv($k); }
    $_ENV['APP_ENV'] = $on ? 'local' : 'production';
    $_ENV['APP_DEBUG'] = $on ? 'true' : 'false';
    Debug::reset();
    Environment::reset();
}

function resetRequestId(array $server = []): void {
    foreach (['REQUEST_ID', 'HTTP_X_REQUEST_ID', 'TRACE_ID', 'HTTP_TRACE_ID'] as $k) { unset($_SERVER[$k]); }
    foreach ($server as $k => $v) { $_SERVER[$k] = $v; }
}

check('jsonPayload 500: response generica non vuota, success/status restano, request_id aggiunto', function () {
    $p = HttpErrorResponse::jsonPayload(500, '', 'abc-1');
    return $p === ['success' => false, 'status' => 500, 'response' => 'Errore interno.', 'request_id' => 'abc-1'];
});

check('jsonPayload 4xx: stessa struttura e testo di prima, nessun request_id', function () {
    return HttpErrorResponse::jsonPayload(404, 'Risorsa non trovata.')
        === ['success' => false, 'status' => 404, 'response' => 'Risorsa non trovata.'];
});

check('RequestId::sanitize accetta solo ^[A-Za-z0-9._-]{1,64}$', function () {
    return RequestId::sanitize('abc.DEF_1-2') === 'abc.DEF_1-2'
        && RequestId::sanitize('') === null
        && RequestId::sanitize('a b') === null
        && RequestId::sanitize("x\r\nSet-Cookie: y") === null
        && RequestId::sanitize(str_repeat('a', 65)) === null
        && RequestId::sanitize(['x']) === null;
});

check('RequestId::current: riusa l\'header valido, rigenera se non valido', function () {
    resetRequestId(['HTTP_X_REQUEST_ID' => 'client-42']);
    $a = RequestId::current();
    resetRequestId(['HTTP_X_REQUEST_ID' => '<script>']);
    $b = RequestId::current();
    $same = RequestId::current() === $b;
    resetRequestId();
    return $a === 'client-42' && preg_match('/^[a-f0-9]{16}$/', $b) === 1 && $same;
});

check('Handler 500 senza debug: messaggio generico + request_id', function () {
    debugMode(false);
    resetRequestId(['HTTP_X_REQUEST_ID' => 'rid-1']);
    [$msg, $rid] = Handler::throwableResponse(500, new RuntimeException('SELECT * FROM users'));
    return $msg === HttpErrorResponse::GENERIC_MESSAGE && $rid === 'rid-1';
});

check('Handler 500 con debug: messaggio dell\'eccezione + request_id', function () {
    debugMode(true);
    resetRequestId(['HTTP_X_REQUEST_ID' => 'rid-2']);
    [$msg, $rid] = Handler::throwableResponse(500, new RuntimeException('dettaglio'));
    return $msg === 'dettaglio' && $rid === 'rid-2';
});

check('Handler 4xx: messaggio invariato e nessun request_id (anche senza debug)', function () {
    debugMode(false);
    [$msg, $rid] = Handler::throwableResponse(404, new RuntimeException('Non trovato', 404));
    [$msg2, $rid2] = Handler::throwableResponse(617, new RuntimeException('Campo non valido', 617));
    return $msg === 'Non trovato' && $rid === null && $msg2 === 'Campo non valido' && $rid2 === null;
});

// Fuori dal sito permissionsApi() non esiste: Handler::run cade su un Error, cioè un 500 reale.
check('Handler::run in sottoprocesso: body 500 con chiavi attese, stesso id nel log', function () {
    $dir = sys_get_temp_dir() . '/wi-api-err-' . bin2hex(random_bytes(4));
    mkdir($dir);
    $autoload = escapeshellarg(realpath(__DIR__ . '/../../vendor/autoload.php'));
    $code = '$_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["HTTP_X_REQUEST_ID"]="sub-77";'
        . 'require ' . $autoload . ';'
        . '\Wonder\Api\Handler::run("/api/x/", "GET", null, function(){ throw new RuntimeException("SELECT segreto"); });';
    $cmd = 'cd ' . escapeshellarg($dir) . ' && APP_ENV=production APP_DEBUG=false php -d display_errors=0 -r ' . escapeshellarg($code) . ' 2>/dev/null';
    $out = (string) shell_exec($cmd);
    $body = json_decode($out, true);
    $log = (string) @file_get_contents($dir . '/storage/logs/error/api.log');
    exec('rm -rf ' . escapeshellarg($dir));
    return is_array($body)
        && array_keys($body) === ['success', 'status', 'response', 'request_id']
        && $body['success'] === false && $body['status'] === 500
        && $body['response'] === 'Errore interno.' && $body['request_id'] === 'sub-77'
        && !str_contains($out, 'segreto') && !str_contains($out, '.php')
        && str_contains($log, '"request_id":"sub-77"');
});

summary();
