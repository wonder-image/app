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

summary();
