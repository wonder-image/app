<?php

/**
 * Test standalone per la derivazione dei nomi di progetto in
 * `php forge config` / `php forge db:init`.
 *
 * Il repo non ha phpunit/pest: esegui con
 *
 *   php tests/Console/ProjectNamingTest.php
 *
 * Regole (vedi docs/superpowers/specs/2026-06-18-project-naming-derivation-design.md):
 * - cartella `<name>-<tld>` (ultimo segmento = TLD se in whitelist)
 * - APP_DOMAIN = dominio CON estensione (`name.tld`)
 * - project name (composer) = kebab, TLD strippato
 * - DB = snake del project name; username = `<snake>_user`
 */

declare(strict_types=1);

use Wonder\Console\Commands\Config;
use Wonder\Console\Commands\LocalEnvironmentCommand;
use Wonder\Console\Commands\LocalStart;

require __DIR__ . '/../../vendor/autoload.php';

/** Espone i metodi protetti di Config per il test. */
final class ConfigProbe extends Config
{
    public function call(string $method, ...$args)
    {
        return $this->$method(...$args);
    }
}

/** Sottoclasse concreta di LocalEnvironmentCommand (abstract) per il test. */
final class LocalEnvProbe extends LocalEnvironmentCommand
{
    public function call(string $method, ...$args)
    {
        return $this->$method(...$args);
    }
}

/** Espone i metodi protetti/privati di LocalStart per il test. */
final class LocalStartProbe extends LocalStart
{
    public function call(string $method, ...$args)
    {
        return $this->$method(...$args);
    }

    /** Esegue `ensureEnv()` (privato) su una cartella e ritorna le coppie del `.env`. */
    public function runEnsureEnv(string $cwd, string $driver = 'herd'): array
    {
        $method = new ReflectionMethod(LocalStart::class, 'ensureEnv');
        $method->invoke($this, $cwd, '127.0.0.1', 8088, $driver, new \Symfony\Component\Console\Output\BufferedOutput());

        $values = [];

        foreach (file($cwd . '/.env', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $m) === 1) {
                $values[$m[1]] = trim($m[2], '"');
            }
        }

        return $values;
    }
}

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
        $e = var_export($expected, true);
        $a = var_export($actual, true);
        throw new RuntimeException(($msg !== '' ? $msg . ': ' : '') . "atteso {$e}, ottenuto {$a}");
    }
}

$config = new ConfigProbe();
$local = new LocalEnvProbe();

// --- domainTld(): ultimo segmento se in whitelist, altrimenti '' ---

check('domainTld: wonderimage-it → it', function () use ($config) {
    assertSame('it', $config->call('domainTld', 'wonderimage-it'));
});

check('domainTld: progetto-site → site (.site è TLD valido)', function () use ($config) {
    assertSame('site', $config->call('domainTld', 'progetto-site'));
});

check('domainTld: acme-shop-com → com', function () use ($config) {
    assertSame('com', $config->call('domainTld', 'acme-shop-com'));
});

check('domainTld: dominio puntato wonderimage.it → it', function () use ($config) {
    assertSame('it', $config->call('domainTld', 'wonderimage.it'));
});

check('domainTld: wonderimage (nessun TLD) → vuoto', function () use ($config) {
    assertSame('', $config->call('domainTld', 'wonderimage'));
});

check('domainTld: acme-foo (segmento non in whitelist) → vuoto', function () use ($config) {
    assertSame('', $config->call('domainTld', 'acme-foo'));
});

// --- defaultProjectLabel(): project name kebab, TLD strippato ---

check('projectLabel: wonderimage-it → wonderimage', function () use ($config) {
    assertSame('wonderimage', $config->call('defaultProjectLabel', 'wonderimage-it'));
});

check('projectLabel: progetto-site → progetto (.site strippato dal nome)', function () use ($config) {
    assertSame('progetto', $config->call('defaultProjectLabel', 'progetto-site'));
});

check('projectLabel: acme-shop-com → acme-shop', function () use ($config) {
    assertSame('acme-shop', $config->call('defaultProjectLabel', 'acme-shop-com'));
});

check('projectLabel: acme-foo → acme-foo (non strippato)', function () use ($config) {
    assertSame('acme-foo', $config->call('defaultProjectLabel', 'acme-foo'));
});

// --- defaultAppDomain(): dominio completo CON estensione dalla cartella ---

check('defaultAppDomain: cartella wonderimage-it → wonderimage.it', function () use ($config) {
    assertSame('wonderimage.it', $config->call('defaultAppDomain', '/srv/sites/wonderimage-it'));
});

check('defaultAppDomain: cartella progetto-site → progetto.site', function () use ($config) {
    assertSame('progetto.site', $config->call('defaultAppDomain', '/srv/sites/progetto-site'));
});

check('defaultAppDomain: cartella acme-shop-com → acme-shop.com', function () use ($config) {
    assertSame('acme-shop.com', $config->call('defaultAppDomain', '/srv/sites/acme-shop-com'));
});

check('defaultAppDomain: cartella senza TLD → vuoto (verrà chiesto)', function () use ($config) {
    assertSame('', $config->call('defaultAppDomain', '/srv/sites/wonderimage'));
});

check('defaultAppDomain: cartella con segmento non-TLD → vuoto', function () use ($config) {
    assertSame('', $config->call('defaultAppDomain', '/srv/sites/acme-foo'));
});

// --- projectName(): nome progetto dalla cartella, kebab CON TLD ---

check('projectName: dalla cartella wonderimage-it → wonderimage-it (TLD tenuto)', function () use ($config) {
    assertSame('wonderimage-it', $config->call('projectName', '/srv/sites/wonderimage-it', ''));
});

check('projectName: progetto-site → progetto-site (TLD tenuto)', function () use ($config) {
    assertSame('progetto-site', $config->call('projectName', '/srv/sites/progetto-site', ''));
});

check('projectName: cartella inutilizzabile → fallback dal dominio (kebab con TLD)', function () use ($config) {
    assertSame('wonderimage-it', $config->call('projectName', '/', 'wonderimage.it'));
});

check('composer name: wonder-image/<projectName kebab con TLD>', function () use ($config) {
    $label = $config->call('projectName', '/srv/sites/acme-shop-com', '');
    assertSame('wonder-image/acme-shop-com', 'wonder-image/' . $label);
});

// --- DB: snake del project name (TLD tenuto), username con _user ---

check('DB name: wonderimage-it → wonderimage_it (snake con TLD)', function () use ($local) {
    assertSame('wonderimage_it', $local->call('deriveDatabaseNameFromAppDomain', 'wonderimage-it'));
});

check('DB name: dal dominio wonderimage.it → wonderimage_it', function () use ($local) {
    assertSame('wonderimage_it', $local->call('deriveDatabaseNameFromAppDomain', 'wonderimage.it'));
});

check('DB name: acme-shop-com → acme_shop_com (snake con TLD)', function () use ($local) {
    assertSame('acme_shop_com', $local->call('deriveDatabaseNameFromAppDomain', 'acme-shop-com'));
});

check('DB name: progetto-site → progetto_site', function () use ($local) {
    assertSame('progetto_site', $local->call('deriveDatabaseNameFromAppDomain', 'progetto-site'));
});

check('DB username: da acme-shop-com → acme_shop_com_user', function () use ($local) {
    $db = $local->call('deriveDatabaseNameFromAppDomain', 'acme-shop-com');
    assertSame('acme_shop_com_user', $local->call('buildDefaultDbUsername', $db));
});

// --- updateComposerName(): scrive wonder-image/<kebab> nel composer.json ---

check('updateComposerName: scrive wonder-image/acme-shop-com (kebab con TLD)', function () use ($config) {
    $tmp = sys_get_temp_dir() . '/wi-composer-' . getmypid() . '-' . uniqid() . '.json';
    file_put_contents($tmp, json_encode(['name' => 'wonder-image/app'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    $output = new \Symfony\Component\Console\Output\BufferedOutput();
    $ok = $config->call('updateComposerName', $tmp, 'acme-shop-com', $output);

    $written = json_decode((string) file_get_contents($tmp), true);
    @unlink($tmp);

    assertSame(true, $ok, 'updateComposerName deve avere successo');
    assertSame('wonder-image/acme-shop-com', $written['name'] ?? null);
});

// --- forge start: APP_DOMAIN già impostato non va riscritto dalla cartella ---

$start = new LocalStartProbe();

check('startAppDomain: ecommerce.test in cartella ecommerce-site resta ecommerce.test', function () use ($start) {
    assertSame('ecommerce.test', $start->call('startAppDomain', 'ecommerce.test', '/x/ecommerce-site'));
});

check('startAppDomain: dominio di produzione diverso dalla cartella resta invariato', function () use ($start) {
    assertSame('cliente.it', $start->call('startAppDomain', 'cliente.it', '/x/ecommerce-site'));
});

check('startAppDomain: vuoto → dedotto dalla cartella', function () use ($start) {
    assertSame('wonderimage.it', $start->call('startAppDomain', '', '/x/wonderimage-it'));
});

check('startAppDomain: segnaposto example.local → dedotto dalla cartella', function () use ($start) {
    assertSame('wonderimage.it', $start->call('startAppDomain', 'example.local', '/x/wonderimage-it'));
});

check('startAppDomain: legacy senza estensione (wonderimage) → migrato', function () use ($start) {
    assertSame('wonderimage.it', $start->call('startAppDomain', 'wonderimage', '/x/wonderimage-it'));
});

check('startAppDomain: legacy senza estensione e cartella senza TLD → resta com\'è', function () use ($start) {
    assertSame('acme', $start->call('startAppDomain', 'acme', '/x/acme-foo'));
});

check('forge start (ensureEnv): non riscrive APP_DOMAIN=ecommerce.test nel .env', function () use ($start) {
    $dir = sys_get_temp_dir() . '/wi-start-' . getmypid() . '-' . uniqid() . '/ecommerce-site';
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/.env', "APP_DOMAIN=ecommerce.test\nAPP_URL=https://ecommerce.test\n");

    $env = $start->runEnsureEnv($dir);

    @unlink($dir . '/.env');
    @rmdir($dir);
    @rmdir(dirname($dir));

    assertSame('ecommerce.test', $env['APP_DOMAIN'] ?? null);
    // Senza Herd (CI) il driver ricade su host:porta: l'atteso segue il driver reale.
    assertSame($start->call('resolveLocalAppUrl', 'ecommerce.test', '127.0.0.1', 8088, 'herd'), $env['APP_URL'] ?? null);
});

check('forge start (ensureEnv): APP_DOMAIN=example.local viene compilato dalla cartella', function () use ($start) {
    $dir = sys_get_temp_dir() . '/wi-start-' . getmypid() . '-' . uniqid() . '/wonderimage-it';
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/.env', "APP_DOMAIN=example.local\nAPP_URL=http://127.0.0.1:8088\n");

    $env = $start->runEnsureEnv($dir);

    @unlink($dir . '/.env');
    @rmdir($dir);
    @rmdir(dirname($dir));

    assertSame('wonderimage.it', $env['APP_DOMAIN'] ?? null);
    assertSame($start->call('resolveLocalAppUrl', 'wonderimage.it', '127.0.0.1', 8088, 'herd'), $env['APP_URL'] ?? null);
});

echo "\n";
echo "Totale: {$tests}, fallimenti: {$failures}\n";

exit($failures === 0 ? 0 : 1);
