<?php

/**
 * Test standalone: `forge deploy:prepare` e `forge deploy:activate`.
 *
 *   php tests/Console/DeployCommandsTest.php
 *
 * Senza database: `runBuild()` e `runUpdate()` sono sostituiti da sonde.
 * - prepare: manifesto scritto per ultimo, id non valido e build fallita lo impediscono;
 * - activate: senza manifesto rifiuta; update fallito/locked non sposta il link;
 *   update riuscito sposta il link in modo atomico e salva lo storico;
 *   rilancio idempotente; rollback sposta solo il link.
 */

declare(strict_types=1);

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Wonder\App\Deploy\ReleaseManifest;
use Wonder\App\Deploy\ReleaseSwitcher;
use Wonder\Console\Commands\DeployActivate;
use Wonder\Console\Commands\DeployPrepare;

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

function tmpDir(): string
{
    $dir = sys_get_temp_dir().'/wi-deploy-'.bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);

    return realpath($dir);
}

function rrmdir(string $dir): void
{
    if (is_link($dir) || is_file($dir)) {
        @unlink($dir);
        return;
    }
    foreach (scandir($dir) ?: [] as $item) {
        if ($item !== '.' && $item !== '..') {
            rrmdir($dir.'/'.$item);
        }
    }
    @rmdir($dir);
}

/** Crea una cartella release finta con vendor e handler. */
function makeRelease(string $site, string $id, bool $manifest = true): string
{
    $dir = $site.'/releases/'.$id;
    mkdir($dir.'/vendor/wonder-image/app', 0777, true);
    mkdir($dir.'/handler', 0777, true);
    file_put_contents($dir.'/vendor/autoload.php', '<?php');
    file_put_contents($dir.'/vendor/wonder-image/app/wonder-image.php', '<?php');
    file_put_contents($dir.'/handler/index.php', '<?php');

    if ($manifest) {
        ReleaseManifest::write($dir, $id);
    }

    return $dir;
}

final class PrepareProbe extends DeployPrepare
{
    public int $buildCode = 0;
    public int $buildCalls = 0;

    protected function runBuild(OutputInterface $output, bool $force, bool $forceWww): int
    {
        $this->buildCalls++;
        return $this->buildCode;
    }
}

final class ActivateProbe extends DeployActivate
{
    public array $result = ['success' => true, 'status' => 'success', 'message' => 'ok'];
    public array $calls = [];

    protected function runUpdate(string $releaseDir, string $release, bool $backup, OutputInterface $output): array
    {
        $this->calls[] = [$release, $backup];
        return $this->result;
    }
}

function runPrepare(string $dir, array $input, ?PrepareProbe $probe = null): array
{
    $probe ??= new PrepareProbe();
    $app = new Application();
    $app->add($probe);
    $tester = new CommandTester($probe);
    $old = getcwd();
    chdir($dir);
    $code = $tester->execute($input);
    chdir($old);

    return [$code, $tester->getDisplay(), $probe];
}

function runActivate(array $input, ?ActivateProbe $probe = null): array
{
    $probe ??= new ActivateProbe();
    $tester = new CommandTester($probe);
    $code = $tester->execute($input);

    return [$code, $tester->getDisplay(), $probe];
}

echo "forge deploy:prepare\n";

check('prepare scrive il manifesto dopo la build', function () {
    $dir = makeRelease(tmpDir(), 'abc123', false);
    [$code, , $probe] = runPrepare($dir, ['--release' => 'abc123']);
    assertSame(0, $code);
    assertSame(1, $probe->buildCalls);
    $manifest = ReleaseManifest::read($dir);
    assertSame('abc123', $manifest['release']);
    assertSame(1, $manifest['format']);
    rrmdir(dirname(dirname($dir)));
});

check('prepare con build fallita non scrive il manifesto', function () {
    $dir = makeRelease(tmpDir(), 'abc123', false);
    $probe = new PrepareProbe();
    $probe->buildCode = 1;
    [$code] = runPrepare($dir, ['--release' => 'abc123'], $probe);
    assertSame(1, $code);
    assertTrue(!is_file($dir.'/'.ReleaseManifest::FILE), 'manifesto scritto nonostante la build fallita');
    rrmdir(dirname(dirname($dir)));
});

check('prepare rifiuta un identificativo non valido', function () {
    $dir = makeRelease(tmpDir(), 'abc123', false);
    [$code, , $probe] = runPrepare($dir, ['--release' => '../evil']);
    assertSame(1, $code);
    assertSame(0, $probe->buildCalls);
    rrmdir(dirname(dirname($dir)));
});

check('prepare senza vendor chiede composer install', function () {
    $dir = tmpDir();
    [$code, $out] = runPrepare($dir, ['--release' => 'abc']);
    assertSame(1, $code);
    assertTrue(str_contains($out, 'composer install'), 'messaggio assente');
    rrmdir($dir);
});

check('prepare avvisa se .env è nella release senza stamparne il contenuto', function () {
    $dir = makeRelease(tmpDir(), 'abc123', false);
    file_put_contents($dir.'/.env', "SEGRETO=valore-riservato\n");
    [$code, $out] = runPrepare($dir, ['--release' => 'abc123']);
    assertSame(0, $code);
    assertTrue(str_contains($out, '.env presente'), 'avviso assente');
    assertTrue(!str_contains($out, 'valore-riservato'), 'valore stampato');
    rrmdir(dirname(dirname($dir)));
});

echo "forge deploy:activate\n";

check('activate senza manifesto rifiuta e non sposta il link', function () {
    $site = tmpDir();
    $dir = makeRelease($site, 'r1', false);
    [$code, , $probe] = runActivate(['--release-dir' => $dir]);
    assertSame(1, $code);
    assertSame([], $probe->calls);
    assertTrue(!is_link($site.'/current'), 'link creato');
    rrmdir($site);
});

check('activate con update riuscito sposta il link e salva lo stato', function () {
    $site = tmpDir();
    $dir = makeRelease($site, 'r1');
    [$code, $out, $probe] = runActivate(['--release-dir' => $dir]);
    assertSame(0, $code);
    assertSame([['r1', false]], $probe->calls);
    assertSame($site.'/releases/r1', realpath($site.'/current'));
    assertSame('releases/r1', readlink($site.'/current'));
    assertSame('r1', (new ReleaseSwitcher($site.'/current'))->state()['current']);
    assertTrue(str_contains($out, '"activated"'), 'esito assente');
    rrmdir($site);
});

check('activate con update fallito lascia il link sulla release precedente', function () {
    $site = tmpDir();
    $r1 = makeRelease($site, 'r1');
    $r2 = makeRelease($site, 'r2');
    runActivate(['--release-dir' => $r1]);

    $probe = new ActivateProbe();
    $probe->result = ['success' => false, 'status' => 'failed', 'message' => 'migrazione'];
    [$code] = runActivate(['--release-dir' => $r2], $probe);
    assertSame(1, $code);
    assertSame($site.'/releases/r1', realpath($site.'/current'));
    rrmdir($site);
});

check('activate con update bloccato (locked) non sposta il link', function () {
    $site = tmpDir();
    $r1 = makeRelease($site, 'r1');
    $probe = new ActivateProbe();
    $probe->result = ['success' => false, 'status' => 'locked', 'message' => 'Update già in esecuzione.'];
    [$code, $out] = runActivate(['--release-dir' => $r1], $probe);
    assertSame(1, $code);
    assertTrue(str_contains($out, 'locked'), 'stato non riportato');
    assertTrue(!is_link($site.'/current'), 'link creato');
    rrmdir($site);
});

check('activate è idempotente: rilancio dopo successo', function () {
    $site = tmpDir();
    $r1 = makeRelease($site, 'r1');
    runActivate(['--release-dir' => $r1]);
    $probe = new ActivateProbe();
    $probe->result = ['success' => true, 'status' => 'skipped', 'message' => 'Release già applicata.'];
    [$code] = runActivate(['--release-dir' => $r1], $probe);
    assertSame(0, $code);
    assertSame($site.'/releases/r1', realpath($site.'/current'));
    assertSame(['r1'], (new ReleaseSwitcher($site.'/current'))->state()['history']);
    rrmdir($site);
});

check('activate non fa il backup se non richiesto', function () {
    $site = tmpDir();
    $r1 = makeRelease($site, 'r1');
    [, , $probe] = runActivate(['--release-dir' => $r1]);
    assertSame([['r1', false]], $probe->calls);
    rrmdir($site);
});

check('activate con --backup lo chiede all\'update', function () {
    $site = tmpDir();
    $r1 = makeRelease($site, 'r1');
    [, , $probe] = runActivate(['--release-dir' => $r1, '--backup' => true]);
    assertSame([['r1', true]], $probe->calls);
    rrmdir($site);
});

check('activate rifiuta di sostituire una directory reale chiamata current', function () {
    $site = tmpDir();
    $r1 = makeRelease($site, 'r1');
    mkdir($site.'/current');
    [$code] = runActivate(['--release-dir' => $r1]);
    assertSame(1, $code);
    assertTrue(is_dir($site.'/current') && !is_link($site.'/current'), 'directory sostituita');
    rrmdir($site);
});

check('dry-run non esegue update né sposta il link', function () {
    $site = tmpDir();
    $r1 = makeRelease($site, 'r1');
    [$code, , $probe] = runActivate(['--release-dir' => $r1, '--dry-run' => true]);
    assertSame(0, $code);
    assertSame([], $probe->calls);
    assertTrue(!is_link($site.'/current'), 'link creato');
    rrmdir($site);
});

check('rollback torna alla release precedente senza update', function () {
    $site = tmpDir();
    $r1 = makeRelease($site, 'r1');
    $r2 = makeRelease($site, 'r2');
    runActivate(['--release-dir' => $r1]);
    runActivate(['--release-dir' => $r2]);
    assertSame($site.'/releases/r2', realpath($site.'/current'));

    $probe = new ActivateProbe();
    [$code, $out] = runActivate(['--release-dir' => $r2, '--rollback' => true], $probe);
    assertSame(0, $code);
    assertSame([], $probe->calls);
    assertSame($site.'/releases/r1', realpath($site.'/current'));
    assertTrue(str_contains($out, 'rolled-back'), 'esito assente');
    rrmdir($site);
});

check('rollback con --to verso release inesistente fallisce e non sposta', function () {
    $site = tmpDir();
    $r1 = makeRelease($site, 'r1');
    runActivate(['--release-dir' => $r1]);
    [$code] = runActivate(['--release-dir' => $r1, '--rollback' => true, '--to' => 'zzz']);
    assertSame(1, $code);
    assertSame($site.'/releases/r1', realpath($site.'/current'));
    rrmdir($site);
});

check('il link non sparisce mai durante molti scambi', function () {
    $site = tmpDir();
    $dirs = [makeRelease($site, 'a'), makeRelease($site, 'b')];
    for ($i = 0; $i < 20; $i++) {
        runActivate(['--release-dir' => $dirs[$i % 2]]);
        assertTrue(is_link($site.'/current') && realpath($site.'/current') !== false, 'link assente o rotto');
    }
    assertSame([], glob($site.'/current.tmp-*') ?: [], 'link temporanei rimasti');
    rrmdir($site);
});

check('il manifesto non accetta identificativi pericolosi', function () {
    foreach (['', '.', '..', '../x', 'a/b', 'a b', str_repeat('a', 101)] as $bad) {
        assertTrue(!ReleaseManifest::validId($bad), 'accettato: '.$bad);
    }
    assertTrue(ReleaseManifest::validId('4940da5d1f'), 'sha rifiutato');
    assertTrue(ReleaseManifest::validId('v2.6.1-rc.1'), 'versione rifiutata');
});

echo "\n{$tests} test, {$failures} falliti\n";
exit($failures > 0 ? 1 : 0);
