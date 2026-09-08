<?php

/**
 * Test standalone per la risoluzione di APP_URL in `php forge config`.
 *
 * Il repo non ha phpunit/pest: esegui con
 *
 *   php tests/Console/ConfigAppUrlTest.php
 *
 * Regressione (bug): `forge config` gira a ogni `composer update`
 * (post-update-cmd) e sovrascriveva SEMPRE APP_URL con l'URL di produzione
 * (`https://<dominio.tld>`), cancellando il valore locale `.test` scritto da
 * `forge start` / `forge db:init`. Poiché tutto il runtime costruisce le URL
 * da APP_URL (vedi class/App/Path.php), in locale asset/API/link puntavano
 * alla produzione.
 *
 * Comportamento atteso (come `db:init`/`start`):
 * - APP_URL già valorizzato → non toccarlo (null = nessuna modifica);
 * - APP_URL mancante in locale → URL locale (Herd `.test` o host:port), MAI
 *   quello di produzione;
 * - APP_URL mancante in CI → URL di produzione.
 */

declare(strict_types=1);

use Wonder\Console\Commands\Config;

require __DIR__ . '/../../vendor/autoload.php';

/** Espone i metodi protetti di Config per il test. */
final class ConfigAppUrlProbe extends Config
{
    public function call(string $method, ...$args)
    {
        return $this->$method(...$args);
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

$config = new ConfigAppUrlProbe();

// --- Non-CI: un APP_URL locale già presente NON va sovrascritto ---

check('locale: APP_URL .test già presente → nessuna modifica (null)', function () use ($config) {
    assertSame(null, $config->call('resolveConfigAppUrl', 'https://wonderimage.test', 'wonderimage.it', false));
});

check('locale: APP_URL host:port già presente → nessuna modifica (null)', function () use ($config) {
    assertSame(null, $config->call('resolveConfigAppUrl', 'http://127.0.0.1:8088', 'wonderimage.it', false));
});

// --- Non-CI + APP_URL mancante → URL locale, MAI produzione (questo è il bug) ---

check('locale: APP_URL mancante → URL locale, mai produzione', function () use ($config) {
    $url = $config->call('resolveConfigAppUrl', '', 'wonderimage.it', false);
    // Herd → https://wonderimage.test ; php built-in → http://127.0.0.1:8088.
    // In nessun caso deve essere l'URL di produzione https://wonderimage.it.
    assertSame(
        true,
        in_array($url, ['https://wonderimage.test', 'http://127.0.0.1:8088'], true),
        'atteso URL locale (.test o host:port), ottenuto ' . var_export($url, true)
    );
});

// --- CI: APP_URL mancante → URL di produzione ---

check('CI: APP_URL mancante → URL di produzione', function () use ($config) {
    assertSame('https://wonderimage.it', $config->call('resolveConfigAppUrl', '', 'wonderimage.it', true));
});

// --- CI: APP_URL già presente → nessuna modifica ---

check('CI: APP_URL già presente → nessuna modifica (null)', function () use ($config) {
    assertSame(null, $config->call('resolveConfigAppUrl', 'https://wonderimage.it', 'wonderimage.it', true));
});

echo "\n";
echo "Totale: {$tests}, fallimenti: {$failures}\n";

exit($failures === 0 ? 0 : 1);
