<?php
/** php tests/Console/NginxConfigTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Symfony\Component\Console\Tester\CommandTester;
use Wonder\Console\Commands\Build;
use Wonder\Console\NginxConfig;

$fixtures = __DIR__.'/../fixtures/nginx/';

function nginxTmpRoot(): string
{
    $root = sys_get_temp_dir().'/wi-nginx-'.bin2hex(random_bytes(4));
    mkdir($root, 0777, true);

    return $root;
}

function nginxRemove(string $dir): void
{
    exec('rm -rf '.escapeshellarg($dir));
}

/** Estrae il regex di una `location ~* "..."` che contiene `$needle` e lo converte per PCRE di PHP. */
function nginxRegexContaining(string $conf, string $needle): ?string
{
    foreach (explode("\n", $conf) as $line) {
        if (preg_match('/^\s*location ~\* "(.*)" \{/', $line, $m) === 1 && str_contains($m[1], $needle)) {
            return '~'.str_replace('~', '\~', $m[1]).'~i';
        }
    }

    return null;
}

/** Esegue `forge build` in una cartella temporanea con `APP_WEBSERVER` impostata (null = assente). */
function nginxBuildWith(?string $webServer, array $input = []): array
{
    $root = nginxTmpRoot();
    $previous = getcwd();
    $env = $_ENV['APP_WEBSERVER'] ?? null;
    unset($_ENV['APP_WEBSERVER'], $_SERVER['APP_WEBSERVER']);
    putenv($webServer === null ? 'APP_WEBSERVER' : 'APP_WEBSERVER='.$webServer);
    try {
        chdir($root);
        $tester = new CommandTester(new Build());
        $code = $tester->execute($input);
        chdir((string) $previous);

        return [$root, $code, $tester->getDisplay()];
    } finally {
        chdir((string) $previous);
        putenv('APP_WEBSERVER');
        if ($env !== null) {
            $_ENV['APP_WEBSERVER'] = $env;
        }
    }
}

check('uscita di default uguale al file atteso', fn () => NginxConfig::render() === file_get_contents($fixtures.'wonder.conf'));
check('uscita con force-www e HSTS uguale al file atteso', fn () => NginxConfig::render(true, true) === file_get_contents($fixtures.'wonder-www1-hsts1.conf'));

check('uscita deterministica: niente percorsi assoluti né date', function () {
    $a = NginxConfig::render(true, true);

    return $a === NginxConfig::render(true, true)
        && preg_match('~/Users/|/tmp/|/var/|\d{4}-\d{2}-\d{2}~', $a) === 0;
});

check('force-www e HSTS compaiono solo se richiesti', function () {
    $base = NginxConfig::render();
    $full = NginxConfig::render(true, true);

    return !str_contains($base, 'www.$host') && !str_contains($base, 'Strict-Transport-Security')
        && str_contains($full, 'return 301 https://www.$host$request_uri;')
        && str_contains($full, 'add_header Strict-Transport-Security "max-age=31536000";');
});

check('parità con Build: ogni cartella protetta è negata', function () {
    $conf = NginxConfig::render();
    foreach (Build::PROTECTED_DIRECTORIES as $d) {
        if (!str_contains($conf, 'location ^~ /'.$d.'/ { return 403; }') || !str_contains($conf, 'location = /'.$d.' { return 403; }')) {
            echo "    manca: $d\n";
            return false;
        }
    }
    return true;
});

check('parità con Build: ogni nome di file negato è nel regex nginx', function () {
    $conf = NginxConfig::render();
    foreach (Build::DENIED_FILE_PATTERNS as $p) {
        if (!str_contains($conf, str_replace('.*', '[^/]*', $p))) {
            echo "    manca: $p\n";
            return false;
        }
    }
    return true;
});

check('parità con Build: header, cache, archivi, vendor ed eseguibili dell\'upload', function () {
    $conf = NginxConfig::render(false, true);
    foreach (Build::STATIC_SECURITY_HEADERS as $name => $value) {
        if (!str_contains($conf, 'add_header '.$name.' "'.$value.'";')) {
            echo "    manca header: $name\n";
            return false;
        }
    }
    foreach ([Build::CACHE_ASSET_CONTROL, Build::CACHE_VERSIONED_CONTROL, Build::CACHE_HTML_CONTROL, Build::CACHE_ASSET_EXTENSIONS,
        Build::ARCHIVE_EXTENSIONS, Build::VENDOR_ASSET_EXTENSIONS, Build::VENDOR_SCRIPT_EXTENSIONS, Build::UPLOAD_EXECUTABLE_EXTENSIONS,
        Build::HSTS_VALUE] as $fragment) {
        if (!str_contains($conf, $fragment)) {
            echo "    manca: $fragment\n";
            return false;
        }
    }
    // Gli stessi valori stanno anche nel .htaccess.
    $apache = Build::htaccessTemplate(false, true);
    foreach ([Build::CACHE_ASSET_CONTROL, Build::CACHE_VERSIONED_CONTROL, Build::CACHE_HTML_CONTROL, Build::HSTS_VALUE] as $fragment) {
        if (!str_contains($apache, $fragment)) {
            echo "    manca nel .htaccess: $fragment\n";
            return false;
        }
    }
    return true;
});

check('regex dei file negati: stessi verdetti di Apache', function () {
    $conf = NginxConfig::render();
    $nginx = nginxRegexContaining($conf, '\.env[^/]*|');
    preg_match('/<FilesMatch "([^"]+)">/', Build::htaccessSecurityBlock(), $m, 0, strpos(Build::htaccessSecurityBlock(), '<FilesMatch "(?i)^(\.env'));
    $apache = '~'.str_replace('~', '\~', preg_replace('/^\(\?i\)/', '', $m[1])).'~i';
    $cases = [
        '.env' => true, '.env.local' => true, '.git' => true, '.gitignore' => true, '.htaccess' => true, '.htpasswd' => true,
        'app.log' => true, 'app.log.1' => true, 'dump.sql' => true, 'dump.sql.gz' => true, 'a.bak' => true, 'a.old' => true,
        'a.orig' => true, 'a.swp' => true, 'index.php~' => true, 'composer.json' => true, 'composer.lock' => true,
        'package.json' => true, 'package-lock.json' => true, 'skills-lock.json' => true, 'sync-data.json' => true,
        'generator.conf' => true, 'forge' => true, 'index.php' => false, 'logo.png' => false, 'forge.png' => false,
        'catalog.sql.txt' => false, 'style.css' => false, 'robots.txt' => false, 'environment.php' => false,
    ];
    foreach ($cases as $file => $denied) {
        $inApache = preg_match($apache, $file) === 1;
        $inNginx = preg_match($nginx, '/dir/'.$file) === 1;
        if ($inApache !== $denied || $inNginx !== $denied) {
            echo "    $file: attesa=".(int) $denied.' apache='.(int) $inApache.' nginx='.(int) $inNginx."\n";
            return false;
        }
    }
    return true;
});

check('regex di vendor: asset dei pacchetti e xml-sitemaps ammessi, il resto negato', function () {
    $re = nginxRegexContaining(NginxConfig::render(), '^/vendor(?:/|$)(?!');
    if ($re === null) {
        return false;
    }
    $denied = ['/vendor/autoload.php', '/vendor/composer/installed.json', '/vendor/wonder-image/app/class/Http/Foo.php',
        '/vendor/wonder-image/app/resources/assets/x.php', '/vendor/a/b/resources/assets/shell.php.css', '/vendor/a/b/resources/assets/x.phtml', '/vendor'];
    $allowed = ['/vendor/wonder-image/app/resources/assets/css/a.css', '/vendor/a/b/resources/assets/js/x.js',
        '/vendor/a/b/resources/assets/fonts/f.woff2', '/vendor/wonder-image/app/vendor-static/xml-sitemaps/runcrawl.php'];
    foreach ($denied as $path) {
        if (preg_match($re, $path) !== 1) { echo "    non negato: $path\n"; return false; }
    }
    foreach ($allowed as $path) {
        if (preg_match($re, $path) === 1) { echo "    negato: $path\n"; return false; }
    }
    return true;
});

check('regex dell\'upload: script negati, media ammessi', function () {
    $re = nginxRegexContaining(NginxConfig::render(), '/\.ht[^/]*$');
    if ($re === null) {
        return false;
    }
    foreach (['/assets/upload/a.php', '/assets/upload/A.PHP', '/assets/upload/x.phtml', '/assets/upload/shell.php.jpg', '/assets/upload/run.sh', '/assets/upload/.htaccess'] as $path) {
        if (preg_match($re, $path) !== 1) { echo "    non negato: $path\n"; return false; }
    }
    foreach (['/assets/upload/foto.jpg', '/assets/upload/doc.pdf', '/assets/upload/phpinfo.png', '/assets/upload/a/b.svg'] as $path) {
        if (preg_match($re, $path) === 1) { echo "    negato: $path\n"; return false; }
    }
    return true;
});

check('upload: ogni location con add_header ripete gli header di sicurezza', function () {
    $conf = NginxConfig::render();
    // Spezza in blocchi `{ ... }` più interni e controlla quelli che dichiarano add_header.
    preg_match_all('/\{([^{}]*)\}/', $conf, $m);
    foreach ($m[1] as $body) {
        if (str_contains($body, 'add_header') && !str_contains($body, 'add_header X-Frame-Options')) {
            echo "    location senza header di sicurezza: ".trim(strtok($body, "\n"))."\n";
            return false;
        }
    }
    return true;
});

check('svg dell\'upload: CSP sandbox al posto di frame-ancestors, senza duplicati', function () {
    $conf = NginxConfig::render();
    preg_match('/location ~\* "\\\\\.svgz\?\$" \{(.*?)\n    \}/s', $conf, $m);

    return isset($m[1]) && substr_count($m[1], 'Content-Security-Policy') === 1 && str_contains($m[1], 'Content-Security-Policy "sandbox"');
});

check('forge build --nginx scrive lo snippet e lascia il .htaccess identico', function () {
    $with = nginxTmpRoot();
    $without = nginxTmpRoot();
    $previous = getcwd();
    try {
        chdir($with);
        $tester = new CommandTester(new Build());
        $codeWith = $tester->execute(['--nginx' => null]);
        chdir($without);
        $codeWithout = (new CommandTester(new Build()))->execute([]);
        chdir((string) $previous);

        $snippet = $with.'/storage/generated/nginx/wonder.conf';
        $ok = $codeWith === 0 && $codeWithout === 0
            && is_file($snippet) && file_get_contents($snippet) === NginxConfig::render()
            && file_get_contents($with.'/.htaccess') === file_get_contents($without.'/.htaccess')
            && !file_exists($without.'/storage/generated/nginx');
    } finally {
        chdir((string) $previous);
        nginxRemove($with);
        nginxRemove($without);
    }

    return $ok;
});

check('forge build --nginx=PERCORSO e --force-www', function () {
    $root = nginxTmpRoot();
    $previous = getcwd();
    try {
        chdir($root);
        $code = (new CommandTester(new Build()))->execute(['--nginx' => 'out/site.conf', '--force-www' => true]);
        chdir((string) $previous);
        $ok = $code === 0 && is_file($root.'/out/site.conf')
            && str_contains((string) file_get_contents($root.'/out/site.conf'), 'return 301 https://www.$host$request_uri;')
            && !file_exists($root.'/storage/generated/nginx/wonder.conf');
    } finally {
        chdir((string) $previous);
        nginxRemove($root);
    }

    return $ok;
});

check('--nginx accetta anche un percorso assoluto', function () {
    $root = nginxTmpRoot();
    $other = nginxTmpRoot();
    $path = Build::writeNginxConfig($root, $other.'/nested/wonder.conf');
    $ok = $path === $other.'/nested/wonder.conf' && is_file($path) && !file_exists($root.'/storage/generated');
    nginxRemove($root);
    nginxRemove($other);

    return $ok;
});

check('la cache ?v=N è calcolata a livello server: `if` solo fuori dalle location con try_files', function () {
    $conf = NginxConfig::render(true, true);
    // Unici `if` dentro una location: quelli di @wonder_router, che non usa try_files.
    $inside = preg_match_all('/^\s+if \(/m', $conf);
    $router = substr($conf, (int) strpos($conf, 'location @wonder_router'));
    $server = substr($conf, 0, (int) strpos($conf, 'location = /.git'));

    return $inside === 2 && substr_count($router, 'if (') === 2 && !str_contains($router, 'try_files')
        && str_contains($server, 'set $wonder_cache_control') && str_contains($server, 'if ($arg_v');
});

check('APP_WEBSERVER assente o apache: .htaccess come prima e nessuno snippet', function () {
    $ok = true;
    foreach ([null, '', 'apache', ' Apache '] as $value) {
        [$root, $code] = nginxBuildWith($value);
        $ok = $ok && $code === 0 && is_file($root.'/.htaccess')
            && file_get_contents($root.'/.htaccess') === Build::htaccessTemplate(false, Build::hstsEnabled()).PHP_EOL
            && !file_exists($root.'/storage/generated/nginx');
        nginxRemove($root);
    }

    return $ok;
});

check('APP_WEBSERVER=nginx: snippet al posto del .htaccess', function () {
    [$root, $code] = nginxBuildWith('nginx');
    $snippet = $root.'/storage/generated/nginx/wonder.conf';
    $ok = $code === 0 && is_file($snippet) && file_get_contents($snippet) === NginxConfig::render()
        && !file_exists($root.'/.htaccess') && !file_exists($root.'/assets/upload/.htaccess');
    nginxRemove($root);

    return $ok;
});

check('APP_WEBSERVER=nginx con --force-www e --nginx=PERCORSO', function () {
    [$root, $code] = nginxBuildWith('nginx', ['--force-www' => true, '--nginx' => 'out/site.conf']);
    $ok = $code === 0 && is_file($root.'/out/site.conf') && !file_exists($root.'/storage/generated/nginx/wonder.conf')
        && str_contains((string) file_get_contents($root.'/out/site.conf'), 'www.$host');
    nginxRemove($root);

    return $ok;
});

check('APP_WEBSERVER non riconosciuta: avviso e apache', function () {
    [$root, $code, $display] = nginxBuildWith('caddy');
    $ok = $code === 0 && is_file($root.'/.htaccess') && !file_exists($root.'/storage/generated/nginx')
        && str_contains($display, 'APP_WEBSERVER non riconosciuta');
    nginxRemove($root);

    return $ok;
});

check('lo snippet è sempre riscritto (a differenza del .htaccess)', function () {
    $root = nginxTmpRoot();
    mkdir($root.'/storage/generated/nginx', 0777, true);
    file_put_contents($root.'/storage/generated/nginx/wonder.conf', "vecchio\n");
    $path = Build::writeNginxConfig($root);
    $ok = $path !== null && file_get_contents($path) === NginxConfig::render();
    nginxRemove($root);

    return $ok;
});

check('nginx -t accetta lo snippet (saltato se nginx non è installato)', function () {
    $bin = trim((string) shell_exec('command -v nginx 2>/dev/null'));
    if ($bin === '') {
        echo "    nginx non trovato: salto\n";
        return true;
    }
    $root = nginxTmpRoot();
    mkdir($root.'/www', 0777, true);
    file_put_contents($root.'/wonder.conf', NginxConfig::render(true, true));
    $temp = '';
    foreach (['client_body', 'proxy', 'fastcgi', 'uwsgi', 'scgi'] as $n) {
        $temp .= $n.'_temp_path '.$root.'/'.$n.'; ';
    }
    file_put_contents($root.'/nginx.conf', "pid $root/nginx.pid;\nerror_log $root/error.log;\nevents {}\nhttp {\n  access_log off;\n  $temp\n"
        ."  server {\n    listen 127.0.0.1:18081;\n    root $root/www;\n    include $root/wonder.conf;\n    location ~ \\.php\$ { return 200; }\n  }\n}\n");
    $out = [];
    exec(escapeshellarg($bin).' -t -p '.escapeshellarg($root).' -c '.escapeshellarg($root.'/nginx.conf').' 2>&1', $out, $code);
    nginxRemove($root);
    if ($code !== 0) {
        echo '    '.implode("\n    ", $out)."\n";
    }

    return $code === 0;
});

summary();
