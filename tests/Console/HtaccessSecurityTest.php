<?php
/** php tests/Console/HtaccessSecurityTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Console\Commands\Build;

const SEC_START = '# WONDER SECURITY START';
const SEC_END = '# WONDER SECURITY END';

function secCount(string $s): int { return substr_count($s, SEC_START); }

$legacyHeaders = <<<'TXT'
<IfModule mod_headers.c>
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set X-Content-Type-Options "nosniff"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
  Header always set X-XSS-Protection "1; mode=block"
</IfModule>
TXT;

$custom = <<<TXT
# custom prima
Redirect 301 /vecchia/ /nuova/

<IfModule mod_rewrite.c>
  RewriteEngine On
# WONDER ROUTER START
  RewriteRule ^ handler/index.php [L,QSA]
# WONDER ROUTER END
</IfModule>

# custom dopo
Header set X-Custom "1"
TXT;

check('template: blocco SECURITY una volta, prima del router, senza X-XSS', function () {
    $t = Build::htaccessTemplate();
    return secCount($t) === 1
        && strpos($t, SEC_END) < strpos($t, 'RewriteRule ^ handler/index.php')
        && !str_contains($t, 'X-XSS-Protection')
        && !str_contains($t, 'geolocation=()')
        && Build::updateHtaccessSecurity($t) === $t;
});

check('HSTS assente di default, presente solo con hsts=true e senza preload', function () {
    $off = Build::htaccessTemplate();
    $on = Build::htaccessTemplate(false, true);
    return !str_contains($off, 'Strict-Transport-Security')
        && str_contains($on, 'Strict-Transport-Security "max-age=31536000"')
        && !str_contains($on, 'preload');
});

check('deny con doppia sintassi Apache 2.2/2.4 e file/cartelle richiesti', function () {
    $b = Build::htaccessSecurityBlock();
    foreach (['Require all denied', 'Order allow,deny', 'Deny from all', 'mod_authz_core.c', '!mod_authz_core.c'] as $n) {
        if (!str_contains($b, $n)) { return false; }
    }
    // La regex del FilesMatch deve negare questi nomi e lasciare passare gli asset.
    preg_match('/<FilesMatch "(\^\(\\\\\.env[^"]+)">/', $b, $m);
    $re = '/'.$m[1].'/';
    foreach (['.env', '.env.local', '.gitignore', '.htaccess', 'error.log', 'dump.sql', 'dump.sql.gz', 'x.bak',
              'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'skills-lock.json',
              'sync-data.json', 'generator.conf', 'forge'] as $f) {
        if (preg_match($re, $f) !== 1) { echo "    non negato: $f\n"; return false; }
    }
    foreach (['index.php', 'style.css', 'logo.png', 'forge.js', 'catalog.json'] as $f) {
        if (preg_match($re, $f) === 1) { echo "    negato per errore: $f\n"; return false; }
    }
    preg_match('/RewriteRule (\^\([^ ]+) - \[F,L\]/', $b, $r);
    $dirRe = '#'.$r[1].'#';
    foreach (['.git/config', '.audit/x.md', 'storage/logs/error.log', 'state/a', 'tests/harness.php', 'docs',
              'node_modules/x/y.js', 'lang/it/pages.json', 'handler/storage/x'] as $p) {
        if (preg_match($dirRe, $p) !== 1) { echo "    cartella non negata: $p\n"; return false; }
    }
    foreach (['handler/index.php', 'assets/0.0/css/a.css', 'api/states/', 'app/docs/components/', 'vendor/wonder-image/app/resources/assets/logo.svg', 'storefront/'] as $p) {
        if (preg_match($dirRe, $p) === 1) { echo "    negato per errore: $p\n"; return false; }
    }
    return !str_contains($b, 'vendor');
});

check('header statici solo fuori dai .php (lookahead del FilesMatch)', function () {
    $re = '/^(?!.*\.php$)/';
    return preg_match($re, 'logo.png') === 1 && preg_match($re, 'index.php') === 0
        && str_contains(Build::htaccessSecurityBlock(), '<FilesMatch "^(?!.*\.php$)">')
        && str_contains(Build::htaccessSecurityBlock(), 'geolocation=(self)');
});

check('blocco assente: inserito in testa, righe custom intatte, idempotente', function () use ($custom) {
    $once = Build::updateHtaccessSecurity($custom);
    return secCount($once) === 1
        && str_starts_with($once, SEC_START)
        && str_contains($once, $custom)
        && Build::updateHtaccessSecurity($once) === $once;
});

check('blocco vecchio con righe custom prima, dentro e dopo: sostituito solo fra i marcatori', function () use ($custom) {
    $old = "# riga custom in testa\n\n".SEC_START."\n# riga custom dentro\nHeader set X-Old \"1\"\n".SEC_END."\n\n".$custom;
    $out = Build::updateHtaccessSecurity($old);
    return secCount($out) === 1
        && str_starts_with($out, "# riga custom in testa\n\n".SEC_START)
        && !str_contains($out, 'X-Old')
        && !str_contains($out, '# riga custom dentro')
        && str_contains($out, $custom)
        && str_contains($out, Build::htaccessSecurityBlock())
        && Build::updateHtaccessSecurity($out) === $out;
});

check('blocco presente aggiornato: hsts cambia senza toccare il resto', function () use ($custom) {
    $a = Build::updateHtaccessSecurity($custom);
    $b = Build::updateHtaccessSecurity($a, true);
    return secCount($b) === 1 && str_contains($b, 'Strict-Transport-Security')
        && str_replace(Build::htaccessSecurityBlock(true), Build::htaccessSecurityBlock(), $b) === $a;
});

check('blocco duplicato: resta solo il primo, aggiornato', function () use ($custom) {
    $dup = SEC_START."\nvecchio\n".SEC_END."\n\n".$custom."\n\n".SEC_START."\naltro\n".SEC_END."\n# coda custom\n";
    $out = Build::updateHtaccessSecurity($dup);
    return secCount($out) === 1
        && str_starts_with($out, Build::htaccessSecurityBlock())
        && str_contains($out, $custom)
        && str_contains($out, '# coda custom')
        && Build::updateHtaccessSecurity($out) === $out;
});

check('vecchio blocco header non marcato: tolto solo se identico', function () use ($custom, $legacyHeaders) {
    $out = Build::updateHtaccessSecurity($custom."\n\n".$legacyHeaders."\n\n# fine\n");
    $edited = str_replace('geolocation=()', 'geolocation=(self)', $legacyHeaders);
    $kept = Build::updateHtaccessSecurity($custom."\n\n".$edited."\n");
    return !str_contains($out, 'X-XSS-Protection') && str_contains($out, '# fine') && str_contains($out, $custom)
        && str_contains($kept, $edited);
});

check('f(f(x)) === f(x) anche dopo il blocco performance (come forge update)', function () use ($custom, $legacyHeaders) {
    $f = fn (string $s) => Build::updateHtaccessSecurity(Build::updateHtaccessPerformance($s));
    $x = $custom."\n".$legacyHeaders;
    return $f($f($x)) === $f($x);
});

check('configuration_file.php su un .htaccess custom: custom preservato, upload creato', function () use ($custom, $legacyHeaders) {
    $root = sys_get_temp_dir().'/wi-htsec-'.bin2hex(random_bytes(4));
    mkdir($root.'/assets/upload', 0777, true);
    $orig = $custom."\n\n".$legacyHeaders."\n";
    file_put_contents($root.'/.htaccess', $orig);
    file_put_contents($root.'/robots.txt', "User-agent: *\n");
    $runner = function (string $ROOT): void {
        $ROOT_APP = __DIR__.'/../../app';
        $PAGE = (object) ['domain' => 'example.test'];
        $_ENV['HOME'] = $ROOT; putenv('HOME='.$ROOT);
        require __DIR__.'/../../app/build/update/configuration_file.php';
    };
    $home = getenv('HOME');
    $runner($root);
    $first = file_get_contents($root.'/.htaccess');
    $runner($root);
    $second = file_get_contents($root.'/.htaccess');
    putenv('HOME='.$home);
    $upload = (string) @file_get_contents($root.'/assets/upload/.htaccess');
    exec('rm -rf '.escapeshellarg($root));
    return secCount($first) === 1 && $first === $second
        && str_contains($first, "# custom prima\nRedirect 301 /vecchia/ /nuova/")
        && str_contains($first, "# custom dopo\nHeader set X-Custom \"1\"")
        && !str_contains($first, 'X-XSS-Protection')
        && str_contains($upload, '# WONDER UPLOAD START');
});

summary();
