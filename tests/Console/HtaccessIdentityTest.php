<?php
/** php tests/Console/HtaccessIdentityTest.php
 *
 * Il `.htaccess` generato da `forge build` (Aruba e shared hosting) deve restare
 * identico byte per byte: le fixture sono la fotografia presa prima di
 * estrarre le liste condivise con il generatore nginx.
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Console\Commands\Build;

$dir = __DIR__.'/../fixtures/htaccess/';

foreach ([[false, false], [true, false], [false, true], [true, true]] as [$www, $hsts]) {
    $file = 'template-www'.(int) $www.'-hsts'.(int) $hsts.'.htaccess';
    check("template .htaccess identico (force-www=".(int) $www.", hsts=".(int) $hsts.')', function () use ($dir, $file, $www, $hsts) {
        return Build::htaccessTemplate($www, $hsts) === file_get_contents($dir.$file);
    });
}

check('blocco performance identico', fn () => Build::htaccessPerformanceBlock() === file_get_contents($dir.'performance.htaccess'));
check('blocco upload identico', fn () => Build::uploadHtaccessBlock() === file_get_contents($dir.'upload.htaccess'));

summary();
