<?php

/**
 * Test standalone per FORCE_WWW e APP_TRUST_PROXY in `php forge provision`.
 *
 *   php tests/Console/ProvisionWwwProxyTest.php
 *
 * - FORCE_WWW di default: true per il dominio principale, false per un
 *   sottodominio (il certificato non copre www.shop.esempio.it).
 * - APP_TRUST_PROXY di default false: si attiva solo se si indica un proxy.
 * - Entrambi finiscono nelle GitHub Variables della repo.
 */

declare(strict_types=1);

use Wonder\Console\Commands\Provision;

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

check('dominio principale: FORCE_WWW di default true', function (): void {
    foreach (['wonderimage.it', 'www.wonderimage.it', 'esempio.co.uk', 'www.esempio.com.au'] as $domain) {
        assertSame(true, Provision::defaultForceWww($domain), $domain);
    }
});

check('sottodominio: FORCE_WWW di default false', function (): void {
    foreach (['ecommerce.wonderimage.it', 'www.ecommerce.wonderimage.it', 'shop.esempio.co.uk', 'a.b.esempio.com'] as $domain) {
        assertSame(false, Provision::defaultForceWww($domain), $domain);
    }
});

check('parseYesNo: sì/no in italiano e inglese, altro null', function (): void {
    foreach (['s', 'si', 'sì', 'S', 'yes', 'true', '1'] as $value) {
        assertSame(true, Provision::parseYesNo($value), $value);
    }
    foreach (['n', 'no', 'NO', 'false', '0'] as $value) {
        assertSame(false, Provision::parseYesNo($value), $value);
    }
    assertSame(null, Provision::parseYesNo(''));
    assertSame(null, Provision::parseYesNo('forse'));
});

check('proxy: vuoto = default, nessuno = false, un proxy = true', function (): void {
    assertSame(false, Provision::trustProxyFromAnswer('', false));
    assertSame(true, Provision::trustProxyFromAnswer('', true));
    assertSame(false, Provision::trustProxyFromAnswer('nessuno', true));
    assertSame(true, Provision::trustProxyFromAnswer('cloudflare', false));
    assertSame(true, Provision::trustProxyFromAnswer('Altro', false));
    assertSame(true, Provision::trustProxyFromAnswer('attivo', false));
});

check('provision manda FORCE_WWW e APP_TRUST_PROXY nelle GitHub Variables', function (): void {
    $source = (string) file_get_contents(__DIR__.'/../../class/Console/Commands/Provision.php');
    assertSame(true, str_contains($source, "'FORCE_WWW' => \$forceWww ? 'true' : 'false',"));
    assertSame(true, str_contains($source, "'APP_TRUST_PROXY' => \$trustProxy ? 'true' : 'false',"));
    assertSame(true, str_contains($source, '$trustProxy = $existingTrustProxy ?? false;'), 'senza risposta né variabile il proxy resta spento');
});

echo "\n";
echo "Totale: {$tests}, fallimenti: {$failures}\n";

exit($failures === 0 ? 0 : 1);
