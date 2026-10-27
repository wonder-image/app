<?php

/**
 * Test standalone per `php forge cloudflare:security` e per APP_PROXY in provision.
 *
 *   php tests/Console/CloudflareSecurityTest.php
 *
 * Nessuna chiamata a Cloudflare: si verificano le regole generate e il piano
 * (crea / aggiungi / aggiorna / ok / bloccata) a partire da ruleset finti.
 */

declare(strict_types=1);

use Wonder\Console\Commands\CloudflareSecurity;
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

check('regola skip: /api/ tranne /api/frontend/, solo securityLevel e bic', function (): void {
    $rule = CloudflareSecurity::apiSkipRule();
    assertSame('skip', $rule['action']);
    assertSame(['securityLevel', 'bic'], $rule['action_parameters']['products']);
    assertSame('(starts_with(http.request.uri.path, "/api/") and not starts_with(http.request.uri.path, "/api/frontend/"))', $rule['expression']);
    assertSame(true, str_starts_with($rule['description'], 'wonder-image'));
});

check('rate limit: parametri ammessi dal piano free', function (): void {
    $rule = CloudflareSecurity::rateLimitRule(60);
    assertSame('block', $rule['action']);
    assertSame(10, $rule['ratelimit']['period']);
    assertSame(10, $rule['ratelimit']['mitigation_timeout']);
    assertSame(['cf.colo.id', 'ip.src'], $rule['ratelimit']['characteristics']);
    assertSame(60, $rule['ratelimit']['requests_per_period']);
    assertSame(false, str_contains($rule['expression'], 'cf.colo.id'), 'cf.colo.id non va nell\'espressione');
    assertSame(1, CloudflareSecurity::rateLimitRule(0)['ratelimit']['requests_per_period']);
});

check('piano: entrypoint assente = create', function (): void {
    assertSame('create', CloudflareSecurity::rulePlan(null, CloudflareSecurity::apiSkipRule(), false));
});

check('piano: regola nostra uguale = ok, diversa = update', function (): void {
    $desired = CloudflareSecurity::apiSkipRule();
    $existing = $desired + ['id' => 'r1', 'version' => '3', 'last_updated' => 'ieri'];
    $existing['action_parameters']['products'] = ['bic', 'securityLevel'];
    assertSame('ok', CloudflareSecurity::rulePlan(['id' => 'rs', 'rules' => [$existing]], $desired, false), 'ordine dei products irrilevante');

    $existing['expression'] = 'starts_with(http.request.uri.path, "/api/")';
    assertSame('update', CloudflareSecurity::rulePlan(['id' => 'rs', 'rules' => [$existing]], $desired, false));
});

check('piano: altre regole = add, ma sul free col limite di una = blocked', function (): void {
    $other = ['id' => 'x', 'ref' => 'altra', 'expression' => 'true', 'action' => 'block'];
    $ruleset = ['id' => 'rs', 'rules' => [$other]];
    assertSame('add', CloudflareSecurity::rulePlan($ruleset, CloudflareSecurity::apiSkipRule(), false));
    assertSame('blocked', CloudflareSecurity::rulePlan($ruleset, CloudflareSecurity::rateLimitRule(60), true));
    assertSame('add', CloudflareSecurity::rulePlan(['id' => 'rs', 'rules' => []], CloudflareSecurity::rateLimitRule(60), true));
});

check('dominio base: www rimosso', function (): void {
    assertSame('esempio.it', CloudflareSecurity::baseDomain('www.esempio.it'));
    assertSame('shop.esempio.it', CloudflareSecurity::baseDomain('shop.esempio.it.'));
});

check('config: default senza file, override solo delle chiavi note', function (): void {
    $dir = sys_get_temp_dir().'/wi-cf-test-'.bin2hex(random_bytes(4));
    assertSame(CloudflareSecurity::DEFAULT_CONFIG, CloudflareSecurity::resolveConfig($dir));
    assertSame(false, CloudflareSecurity::DEFAULT_CONFIG['bot_fight_mode'], 'Bot Fight Mode spento di default');

    mkdir($dir.'/custom/config', 0777, true);
    file_put_contents($dir.'/custom/config/cloudflare.php', "<?php return ['bot_fight_mode' => true, 'sconosciuta' => 1];");
    $config = CloudflareSecurity::resolveConfig($dir);
    unlink($dir.'/custom/config/cloudflare.php');
    rmdir($dir.'/custom/config');
    rmdir($dir.'/custom');
    rmdir($dir);

    assertSame(true, $config['bot_fight_mode']);
    assertSame(false, array_key_exists('sconosciuta', $config));
});

check('provision: risposta proxy → nessuno / cloudflare / altro', function (): void {
    assertSame('cloudflare', Provision::proxyFromAnswer('', 'cloudflare'));
    assertSame('nessuno', Provision::proxyFromAnswer('No', 'cloudflare'));
    assertSame('cloudflare', Provision::proxyFromAnswer('Cloudflare', 'nessuno'));
    assertSame('altro', Provision::proxyFromAnswer('attivo', 'nessuno'));
    assertSame(false, Provision::trustProxyFromAnswer('nessuno', true));
    assertSame(true, Provision::trustProxyFromAnswer('cloudflare', false));
});

check('provision: nameserver Cloudflare rilevati', function (): void {
    assertSame(true, Provision::usesCloudflareNameservers(['ada.ns.cloudflare.com', 'bob.ns.cloudflare.com.']));
    assertSame(false, Provision::usesCloudflareNameservers(['ada.ns.cloudflare.com', 'dns1.registrar.it']));
    assertSame(false, Provision::usesCloudflareNameservers([]));
});

check('provision manda APP_PROXY nelle GitHub Variables', function (): void {
    $source = (string) file_get_contents(__DIR__.'/../../class/Console/Commands/Provision.php');
    assertSame(true, str_contains($source, "'APP_PROXY' => \$proxy,"));
});

echo "\n";
echo "Totale: {$tests}, fallimenti: {$failures}\n";

exit($failures === 0 ? 0 : 1);
