<?php
/** php tests/Console/ForgeDirectoryModeTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Wonder\Console\Commands\Build;

umask(022);

function perms(string $path): string {
    clearstatcache();
    return substr(sprintf('%o', fileperms($path)), -4);
}

check('runtimeDirectoryMode: storage/logs 0750, il resto 0755', function () {
    return Build::runtimeDirectoryMode('/x/storage/logs/') === 0750
        && Build::runtimeDirectoryMode('/x/storage/logs') === 0750
        && Build::runtimeDirectoryMode('/x/storage/cache/') === 0755
        && Build::runtimeDirectoryMode('/x/handler/') === 0755
        && Build::runtimeDirectoryMode('/x/storage/logs-old/') === 0755;
});

check('forge build su una root vuota: cartelle 0755, storage/logs 0750', function () {
    $root = sys_get_temp_dir().'/wi-mode-'.bin2hex(random_bytes(4));
    mkdir($root, 0755);
    $cwd = getcwd();
    chdir($root);
    try {
        (new Build())->run(new ArrayInput([]), new BufferedOutput());
    } finally {
        chdir($cwd);
    }
    $ok = perms($root.'/storage/logs') === '0750';
    foreach (['storage/cache', 'storage/tmp', 'handler', 'api', 'backend', 'bin', 'assets/upload/user/profile-picture'] as $d) {
        if (perms($root.'/'.$d) !== '0755') { echo "    $d: ".perms($root.'/'.$d)."\n"; $ok = false; }
    }
    exec('rm -rf '.escapeshellarg($root));
    return $ok;
});

check('cartella esistente: nessun chmod', function () {
    $root = sys_get_temp_dir().'/wi-mode-'.bin2hex(random_bytes(4));
    mkdir($root.'/storage/logs', 0777, true);
    chmod($root.'/storage/logs', 0777);
    $cwd = getcwd();
    chdir($root);
    try {
        (new Build())->run(new ArrayInput([]), new BufferedOutput());
    } finally {
        chdir($cwd);
    }
    $ok = perms($root.'/storage/logs') === '0777';
    exec('rm -rf '.escapeshellarg($root));
    return $ok;
});

check('niente 0777 in Build e in Command::ensureDir', function () {
    $build = (string) file_get_contents(__DIR__.'/../../class/Console/Commands/Build.php');
    $command = (string) file_get_contents(__DIR__.'/../../class/Console/Command.php');
    return !str_contains($build, '0777') && !str_contains($command, '0777');
});

summary();
