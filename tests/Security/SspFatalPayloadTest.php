<?php
/** php tests/Security/SspFatalPayloadTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\App\Debug;
use Wonder\Backend\Table\SSP;

$detail = "An SQL error occurred: SQLSTATE[42S02]: Table 'shop.gst_secret' doesn't exist";

check('senza debug: solo il messaggio generico', static function () use ($detail): bool {
    $json = json_encode(SSP::fatalPayload(SSP::FATAL_MESSAGE, $detail, false));

    return SSP::fatalPayload(SSP::FATAL_MESSAGE, $detail, false) === ['error' => 'Errore nel caricamento della tabella.']
        && !str_contains($json, 'SQLSTATE')
        && !str_contains($json, 'gst_secret');
});

check('con debug: dettaglio in una chiave separata', static fn () =>
    SSP::fatalPayload(SSP::FATAL_MESSAGE, $detail, true) === ['error' => SSP::FATAL_MESSAGE, 'detail' => $detail]);

check('senza flag segue Debug::enabled()', static function () use ($detail): bool {
    unset($_ENV['APP_DEBUG'], $_SERVER['APP_DEBUG'], $_SERVER['REMOTE_ADDR'], $_SERVER['SERVER_NAME']);
    Debug::reset();
    $off = SSP::fatalPayload(SSP::FATAL_MESSAGE, $detail);

    $_ENV['APP_DEBUG'] = 'true';
    Debug::reset();
    $on = SSP::fatalPayload(SSP::FATAL_MESSAGE, $detail);

    unset($_ENV['APP_DEBUG']);
    Debug::reset();

    return !isset($off['detail']) && ($on['detail'] ?? null) === $detail;
});

check('fatal(): risposta generica e dettaglio nel log backend/list.table', static function () use ($detail): bool {
    $root = sys_get_temp_dir().'/wi-ssp-fatal-'.bin2hex(random_bytes(4));
    mkdir($root, 0775, true);
    $script = $root.'/run.php';
    file_put_contents($script, '<?php
require '.var_export(__DIR__.'/../../vendor/autoload.php', true).';
define("ROOT", '.var_export($root, true).');
\Wonder\Backend\Table\SSP::fatal('.var_export($detail, true).');
echo "NON ESCE";
');
    $out = shell_exec('env -u APP_DEBUG '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' 2>&1');
    $log = (string) @file_get_contents($root.'/storage/logs/error.log');
    $entry = json_decode(trim($log), true);

    @unlink($root.'/storage/logs/error.log');
    @rmdir($root.'/storage/logs');
    @rmdir($root.'/storage');
    @unlink($script);
    @rmdir($root);

    return $out === '{"error":"Errore nel caricamento della tabella."}'
        && is_array($entry)
        && $entry['service'] === 'backend'
        && $entry['action'] === 'list.table'
        && $entry['message'] === $detail;
});

check('castNative: interi e float nativi come con pdo_mysql', static fn () =>
    SSP::castNative('42', 'int') === 42
    && SSP::castNative('-7', 'int') === -7
    && SSP::castNative('18446744073709551615', 'int') === '18446744073709551615'
    && SSP::castNative('1.5', 'float') === 1.5
    && SSP::castNative(null, 'int') === null
    && SSP::nativeKind(MYSQLI_TYPE_LONG) === 'int'
    && SSP::nativeKind(MYSQLI_TYPE_DOUBLE) === 'float'
    && SSP::nativeKind(MYSQLI_TYPE_NEWDECIMAL) === null
    && SSP::nativeKind(MYSQLI_TYPE_VAR_STRING) === null);

check('SSP non usa più PDO e ListProvider passa la connessione', static function (): bool {
    $ssp = (string) file_get_contents(__DIR__.'/../../class/Backend/Table/SSP.php');
    $provider = (string) file_get_contents(__DIR__.'/../../class/Backend/Table/ListProvider.php');

    return preg_match('/\bnew\s+PDO\b|PDO::|use\s+PDO;/', $ssp) === 0
        && str_contains($provider, '$connection = $name->connection ?? null;');
});

summary();
