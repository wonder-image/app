<?php

/**
 * Test standalone per `NPM_GITHUB_TOKEN` in `php forge provision`.
 *
 *   php tests/Console/ProvisionNpmTokenTest.php
 *
 * Nessuna chiamata a GitHub o Bitwarden: si verificano i controlli sui
 * workflow e il link di creazione del token.
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

function tempSite(): string
{
    $dir = sys_get_temp_dir().'/wi-npm-wf-'.bin2hex(random_bytes(4));
    mkdir($dir.'/.github/workflows', 0777, true);

    return $dir;
}

function removeSite(string $dir): void
{
    foreach (glob($dir.'/.github/{workflows,scripts}/*', GLOB_BRACE) ?: [] as $file) {
        unlink($file);
    }
    rmdir($dir.'/.github/workflows');
    if (is_dir($dir.'/.github/scripts')) {
        rmdir($dir.'/.github/scripts');
    }
    rmdir($dir.'/.github');
    rmdir($dir);
}

check('npm: nessun workflow = non serve il token', function (): void {
    $dir = tempSite();
    assertSame([], Provision::workflowsUsingNpmToken($dir));
    removeSite($dir);
});

check('npm: workflow che usa secrets.NPM_GITHUB_TOKEN', function (): void {
    $dir = tempSite();
    file_put_contents($dir.'/.github/workflows/deploy.yml', "steps:\n  - run: npm ci --ignore-scripts\n    env:\n      NODE_AUTH_TOKEN: \${{ secrets.NPM_GITHUB_TOKEN }}\n");
    file_put_contents($dir.'/.github/workflows/altro.yaml', "jobs: {}\n");
    assertSame(['.github/workflows/deploy.yml'], Provision::workflowsUsingNpmToken($dir));
    removeSite($dir);
});

check('npm: una riga solo commentata non conta', function (): void {
    $dir = tempSite();
    file_put_contents($dir.'/.github/workflows/deploy.yml', "steps:\n  # NODE_AUTH_TOKEN: \${{ secrets.NPM_GITHUB_TOKEN }}\n  - run: npm ci\n");
    assertSame([], Provision::workflowsUsingNpmToken($dir));
    removeSite($dir);
});

check('npm: workflow che scrive il .env senza escludere NPM_GITHUB_TOKEN', function (): void {
    $dir = tempSite();
    $file = $dir.'/.github/workflows/deploy.yml';
    $workflow = "case \"\$key\" in\n  FTP_*|BWS_*)\n    ;;\n  *)\n    printf '%s=%s\\n' \"\$key\" \"\$v\" >> .env\n    ;;\nesac\n";

    file_put_contents($file, $workflow);
    assertSame(['.github/workflows/deploy.yml'], Provision::workflowsLeakingKey($dir, 'NPM_GITHUB_TOKEN'), 'senza esclusione');

    file_put_contents($file, str_replace("  FTP_*|BWS_*)\n", "  # NPM_GITHUB_TOKEN)\n  FTP_*|BWS_*)\n", $workflow));
    assertSame(['.github/workflows/deploy.yml'], Provision::workflowsLeakingKey($dir, 'NPM_GITHUB_TOKEN'), 'un commento non basta');

    file_put_contents($file, str_replace('FTP_*|BWS_*)', 'FTP_*|BWS_*|NPM_GITHUB_TOKEN)', $workflow));
    assertSame([], Provision::workflowsLeakingKey($dir, 'NPM_GITHUB_TOKEN'), 'con esclusione');
    assertSame(['.github/workflows/deploy.yml'], Provision::workflowsLeakingCloudflare($dir), 'Cloudflare resta separato');

    file_put_contents($file, "jobs: {}\n");
    assertSame([], Provision::workflowsLeakingKey($dir, 'NPM_GITHUB_TOKEN'), 'non scrive il .env');

    removeSite($dir);
});

check('npm: il filtro in uno script richiamato dal workflow vale', function (): void {
    $dir = tempSite();
    mkdir($dir.'/.github/scripts', 0777, true);
    file_put_contents($dir.'/.github/workflows/deploy.yml', "steps:\n  - run: |\n      bash .github/scripts/secrets-to-env.sh .env\n      echo A=1 >> .env\n");
    $script = $dir.'/.github/scripts/secrets-to-env.sh';
    file_put_contents($script, "case \"\$key\" in\n  FTP_*|BWS_*|CLOUDFLARE_*) ;;\n  *) echo x >> \"\$env_file\" ;;\nesac\n");
    assertSame(['.github/workflows/deploy.yml'], Provision::workflowsLeakingKey($dir, 'NPM_GITHUB_TOKEN'));
    assertSame([], Provision::workflowsLeakingCloudflare($dir));
    file_put_contents($script, "case \"\$key\" in\n  FTP_*|BWS_*|CLOUDFLARE_*|NPM_GITHUB_TOKEN) ;;\n  *) echo x >> \"\$env_file\" ;;\nesac\n");
    assertSame([], Provision::workflowsLeakingKey($dir, 'NPM_GITHUB_TOKEN'));
    removeSite($dir);
});

check('npm: link di creazione con scope e descrizione codificata', function (): void {
    assertSame(
        'https://github.com/settings/tokens/new?scopes=read:packages&description=npm%20wonder-image%2Fnew-site',
        Provision::npmTokenCreationUrl('wonder-image/new-site')
    );
});

echo "\n";
echo "Totale: {$tests}, fallimenti: {$failures}\n";

exit($failures === 0 ? 0 : 1);
