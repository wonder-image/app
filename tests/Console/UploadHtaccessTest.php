<?php
/** php tests/Console/UploadHtaccessTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Console\Commands\Build;

function tmpRoot(): string {
    $root = sys_get_temp_dir().'/wi-upl-'.bin2hex(random_bytes(4));
    mkdir($root.'/assets/upload', 0777, true);
    return $root;
}

check('file assente: creato con il blocco, PHP e .ht* negati', function () {
    $root = tmpRoot();
    $path = Build::writeUploadHtaccess($root);
    $c = (string) file_get_contents($root.'/assets/upload/.htaccess');
    exec('rm -rf '.escapeshellarg($root));
    return $path !== null
        && str_contains($c, '# WONDER UPLOAD START') && str_contains($c, 'php_flag engine off')
        && str_contains($c, 'Require all denied') && str_contains($c, 'Deny from all');
});

check('regex del deny: script e .ht* negati, media ammessi', function () {
    preg_match('/<FilesMatch "([^"]+)">/', Build::uploadHtaccessBlock(), $m);
    $re = '/'.$m[1].'/';
    foreach (['a.php', 'A.PHP', 'x.phtml', 'x.php5', 'x.phar', 'shell.php.jpg', 'run.sh', '.htaccess', '.htpasswd'] as $f) {
        if (preg_match($re, $f) !== 1) { echo "    non negato: $f\n"; return false; }
    }
    foreach (['foto.jpg', 'doc.pdf', 'logo.svg', 'video.mp4', 'photography.webp'] as $f) {
        if (preg_match($re, $f) === 1) { echo "    negato per errore: $f\n"; return false; }
    }
    return true;
});

check('contenuto custom preservato, blocco aggiunto, idempotente', function () {
    $custom = "# regola del sito\nHeader set X-Upload \"1\"\n";
    $once = Build::updateUploadHtaccess($custom);
    return str_starts_with($once, trim($custom))
        && substr_count($once, '# WONDER UPLOAD START') === 1
        && Build::updateUploadHtaccess($once) === $once;
});

check('blocco vecchio o duplicato: sostituito fra i marcatori, resto intatto', function () {
    $old = "# prima\n# WONDER UPLOAD START\nvecchio\n# WONDER UPLOAD END\n# dopo\n# WONDER UPLOAD START\nx\n# WONDER UPLOAD END\n";
    $out = Build::updateUploadHtaccess($old);
    return substr_count($out, '# WONDER UPLOAD START') === 1
        && str_starts_with($out, "# prima\n".Build::uploadHtaccessBlock())
        && str_contains($out, '# dopo') && !str_contains($out, 'vecchio');
});

check('secondo run non riscrive il file; senza assets/upload non fa nulla', function () {
    $root = tmpRoot();
    Build::writeUploadHtaccess($root);
    $second = Build::writeUploadHtaccess($root);
    $none = sys_get_temp_dir().'/wi-upl-none-'.bin2hex(random_bytes(4));
    mkdir($none);
    $r = Build::writeUploadHtaccess($none);
    exec('rm -rf '.escapeshellarg($root).' '.escapeshellarg($none));
    return $second === null && $r === null && !is_dir($none.'/assets');
});

check('M1: START orfano con righe custom: marker tolto, custom intatto, idempotente', function () {
    $x = "# prima\n# WONDER UPLOAD START\n# regola custom A\nHeader set X-A \"1\"\n# regola custom B\n";
    $once = Build::updateUploadHtaccess($x);
    $twice = Build::updateUploadHtaccess($once);
    return $twice === $once
        && substr_count($once, '# WONDER UPLOAD START') === 1
        && substr_count($once, '# WONDER UPLOAD END') === 1
        && str_contains($twice, "# regola custom A\nHeader set X-A \"1\"\n# regola custom B");
});

check('M1: END orfano tolto, custom intatto, idempotente', function () {
    $x = "# prima\n# WONDER UPLOAD END\n# dopo\n";
    $once = Build::updateUploadHtaccess($x);
    return Build::updateUploadHtaccess($once) === $once
        && substr_count($once, '# WONDER UPLOAD END') === 1
        && str_contains($once, "# prima\n# dopo");
});

check('N1: file CRLF: blocco scritto in CRLF, niente LF singoli, idempotente', function () {
    $x = "# prima\r\nHeader set X-A \"1\"\r\n";
    $once = Build::updateUploadHtaccess($x);
    $old = "# prima\r\n# WONDER UPLOAD START\r\nvecchio\r\n# WONDER UPLOAD END\r\n# dopo\r\n";
    $repl = Build::updateUploadHtaccess($old);
    return preg_match('/(?<!\r)\n/', $once) === 0 && Build::updateUploadHtaccess($once) === $once
        && preg_match('/(?<!\r)\n/', $repl) === 0 && !str_contains($repl, 'vecchio') && str_contains($repl, "# dopo\r\n");
});

check('R2-1: START orfano con testo dopo: marker tolto, custom intatto, idempotente', function () {
    $x = "# prima\n# WONDER UPLOAD START (non toccare)\n# regola custom A\n# regola custom B\n";
    $once = Build::updateUploadHtaccess($x);
    $twice = Build::updateUploadHtaccess($once);
    return $twice === $once
        && substr_count($twice, '# WONDER UPLOAD START') === 1
        && substr_count($twice, '# WONDER UPLOAD END') === 1
        && str_contains($twice, "# regola custom A\n# regola custom B");
});

check('R2-1: SECURITY con START orfano con testo dopo: un solo START, custom intatto', function () {
    $x = "# prima\n# WONDER SECURITY START (non toccare)\n# regola custom A\n";
    $once = Build::updateHtaccessSecurity($x);
    $twice = Build::updateHtaccessSecurity($once);
    return $twice === $once
        && substr_count($twice, '# WONDER SECURITY START') === 1
        && str_contains($twice, "# regola custom A");
});

check('nosniff su tutti i file di upload, CSP sandbox solo sugli SVG, dopo il deny', function () {
    $block = Build::uploadHtaccessBlock();
    $svg = strpos($block, '<FilesMatch "(?i)\\.svgz?$">');
    $svgBlock = $svg === false ? '' : substr($block, $svg, strpos($block, '</FilesMatch>', $svg) - $svg);
    $nosniff = strpos($block, 'Header set X-Content-Type-Options "nosniff"');
    preg_match_all('/<FilesMatch "([^"]+)">/', $block, $m);
    $re = '/'.$m[1][1].'/';
    // nosniff fuori da ogni FilesMatch: fra la fine del deny e l'inizio del blocco svg.
    $outside = $nosniff !== false && $nosniff > strpos($block, '</FilesMatch>') && $nosniff < $svg;
    return $svg !== false && $svg > strpos($block, 'Deny from all') && $outside
        && substr_count($block, 'X-Content-Type-Options') === 1
        && str_contains($svgBlock, 'Header set Content-Security-Policy "sandbox"')
        && substr_count($block, 'Content-Security-Policy') === 1
        && preg_match($re, 'logo.svg') === 1 && preg_match($re, 'LOGO.SVGZ') === 1
        && preg_match($re, 'foto.jpg') !== 1 && preg_match($re, 'logo.svg.png') !== 1;
});

check('blocco del round precedente (nosniff solo sugli svg) sostituito, idempotente', function () {
    $old = "# custom\n# WONDER UPLOAD START\n<FilesMatch \"(?i)\\.svgz?$\">\n  <IfModule mod_headers.c>\n"
        ."    Header set Content-Security-Policy \"sandbox\"\n    Header set X-Content-Type-Options \"nosniff\"\n"
        ."  </IfModule>\n</FilesMatch>\n# WONDER UPLOAD END\n# dopo\n";
    $once = Build::updateUploadHtaccess($old);
    return $once === Build::updateUploadHtaccess($once)
        && substr_count($once, 'X-Content-Type-Options') === 1
        && str_contains($once, Build::uploadHtaccessBlock())
        && str_starts_with($once, "# custom\n") && str_contains($once, "# dopo");
});

summary();
