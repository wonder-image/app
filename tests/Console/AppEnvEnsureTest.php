<?php

/**
 * Test standalone: i comandi forge che scrivono il `.env` garantiscono APP_ENV.
 *
 *   php tests/Console/AppEnvEnsureTest.php
 *
 * - manca o è vuoto: scrive `APP_ENV=local`, lo dice a console, salva il file;
 * - valore ammesso (local/staging/production, anche con maiuscole e spazi):
 *   non lo tocca e non stampa nulla;
 * - valore non ammesso (es. `prod`): non lo sovrascrive, avvisa;
 * - valore già nell'ambiente del processo: non scrive `local` nel `.env`;
 * - `forge start` / `db:init` (readEnvLines) partono da un `.env` senza APP_ENV
 *   e lo completano senza toccare le altre righe.
 */

declare(strict_types=1);

use Symfony\Component\Console\Output\BufferedOutput;
use Wonder\Console\Commands\Config;
use Wonder\Console\Commands\DbInit;

require __DIR__ . '/../../vendor/autoload.php';

$tests = 0;
$failures = 0;

function check(string $name, callable $fn): void
{
    global $tests, $failures;
    $tests++;

    try {
        $fn();
        echo "  PASS  {$name}\n";
    } catch (Throwable $e) {
        $failures++;
        echo "  FAIL  {$name}\n        {$e->getMessage()}\n";
    }
}

function assertSame($expected, $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(($msg !== '' ? $msg.': ' : '').'atteso '.var_export($expected, true).', ottenuto '.var_export($actual, true));
    }
}

function assertTrue(bool $condition, string $msg): void
{
    if (!$condition) {
        throw new RuntimeException($msg);
    }
}

/** Espone i metodi protetti senza avviare il comando. */
final class AppEnvProbe extends Config
{
    public function ensure(array &$lines, string $envPath, BufferedOutput $output): string
    {
        return $this->ensureAppEnv($lines, $envPath, $output);
    }
}

final class DbInitProbe extends DbInit
{
    public function read(string $cwd, BufferedOutput $output): ?array
    {
        return $this->readEnvLines($cwd, $output);
    }
}

function tempDir(): string
{
    $dir = sys_get_temp_dir().'/wi-appenv-'.bin2hex(random_bytes(4));
    mkdir($dir);

    return $dir;
}

function runEnsure(array $lines, ?string $processValue = null): array
{
    $dir = tempDir();
    $path = $dir.'/.env';
    file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);

    unset($_ENV['APP_ENV']);
    putenv('APP_ENV');
    if ($processValue !== null) {
        $_ENV['APP_ENV'] = $processValue;
        putenv('APP_ENV='.$processValue);
    }

    $output = new BufferedOutput();
    $status = (new AppEnvProbe())->ensure($lines, $path, $output);
    $file = (string) file_get_contents($path);

    unset($_ENV['APP_ENV']);
    putenv('APP_ENV');
    unlink($path);
    rmdir($dir);

    return [$status, $file, $output->fetch(), $lines];
}

check('APP_ENV assente: scrive local, avvisa e salva il file', function (): void {
    [$status, $file, $out] = runEnsure(['APP_KEY=abc', 'DB_DATABASE=demo']);
    assertSame('added', $status);
    assertSame("APP_KEY=abc\nDB_DATABASE=demo\nAPP_ENV=local\n", $file);
    assertTrue(str_contains($out, 'APP_ENV=local aggiunto'), 'messaggio a console mancante');
});

check('APP_ENV vuoto: lo sostituisce con local senza duplicare la riga', function (): void {
    [$status, $file] = runEnsure(['APP_ENV=', 'APP_KEY=abc']);
    assertSame('added', $status);
    assertSame("APP_ENV=local\nAPP_KEY=abc\n", $file);
});

check('.env vuoto: crea la sola riga APP_ENV=local', function (): void {
    [$status, $file] = runEnsure([]);
    assertSame('added', $status);
    assertTrue(str_contains($file, 'APP_ENV=local'), 'APP_ENV=local non scritto');
});

check('valori ammessi: non li tocca e non stampa nulla', function (): void {
    foreach (['local', 'staging', 'production', ' Production ', '"staging"'] as $value) {
        [$status, $file, $out] = runEnsure(['APP_ENV='.$value, 'APP_KEY=abc']);
        assertSame('present', $status, $value);
        assertSame("APP_ENV={$value}\nAPP_KEY=abc\n", $file, $value);
        assertSame('', $out, $value);
    }
});

check('valore non ammesso (prod): non lo sovrascrive, avvisa', function (): void {
    [$status, $file, $out] = runEnsure(['APP_ENV=prod']);
    assertSame('invalid', $status);
    assertSame("APP_ENV=prod\n", $file);
    assertTrue(str_contains($out, 'non è valido'), 'avviso mancante');
});

check('valore solo nell\'ambiente del processo: non scrive local nel .env', function (): void {
    [$status, $file, $out] = runEnsure(['APP_KEY=abc'], 'staging');
    assertSame('present', $status);
    assertSame("APP_KEY=abc\n", $file);
    assertSame('', $out);
});

check('seconda esecuzione: idempotente', function (): void {
    [, $file] = runEnsure(['APP_KEY=abc']);
    [$status, $again, $out] = runEnsure(explode("\n", rtrim($file)));
    assertSame('present', $status);
    assertSame($file, $again);
    assertSame('', $out);
});

check('readEnvLines (start, db:init): .env esistente senza APP_ENV viene completato', function (): void {
    $dir = tempDir();
    file_put_contents($dir.'/.env', "APP_KEY=abc\nDB_DATABASE=demo\n");
    unset($_ENV['APP_ENV']);
    putenv('APP_ENV');

    $output = new BufferedOutput();
    $lines = (new DbInitProbe())->read($dir, $output);

    assertTrue(is_array($lines), 'righe non lette');
    assertSame("APP_KEY=abc\nDB_DATABASE=demo\nAPP_ENV=local\n", (string) file_get_contents($dir.'/.env'));
    assertTrue(in_array('APP_ENV=local', $lines, true), 'APP_ENV=local non nelle righe restituite');

    unlink($dir.'/.env');
    rmdir($dir);
});

check('readEnvLines: .env nuovo dal template ha APP_ENV=local', function (): void {
    $dir = tempDir();
    unset($_ENV['APP_ENV']);
    putenv('APP_ENV');

    $lines = (new DbInitProbe())->read($dir, new BufferedOutput());

    assertTrue(is_array($lines) && in_array('APP_ENV=local', $lines, true), 'template senza APP_ENV=local');

    unlink($dir.'/.env');
    rmdir($dir);
});

echo "\n{$tests} test, {$failures} falliti\n";
exit($failures === 0 ? 0 : 1);
