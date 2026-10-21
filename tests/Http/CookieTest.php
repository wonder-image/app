<?php
/** php tests/Http/CookieTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Http\Cookie;

$withTrust = static function (?string $value, callable $fn) {
    unset($_ENV['APP_TRUST_PROXY'], $_SERVER['APP_TRUST_PROXY']);
    putenv('APP_TRUST_PROXY');

    if ($value !== null) {
        $_ENV['APP_TRUST_PROXY'] = $value;
    }

    try {
        return $fn();
    } finally {
        unset($_ENV['APP_TRUST_PROXY']);
    }
};

$https = ['HTTPS' => 'on'];
$http = [];
$proxied = ['HTTP_X_FORWARDED_PROTO' => 'https'];

check('opzioni: SameSite=Lax, HttpOnly e path / di default', function () use ($http) {
    $o = Cookie::options(123, '/', true, $http);

    return $o['samesite'] === 'Lax' && $o['httponly'] === true && $o['path'] === '/' && $o['expires'] === 123 && $o['domain'] === '';
});

check('opzioni: HttpOnly si può spegnere per chi lo chiede', fn () => Cookie::options(1, '/', false, [])['httponly'] === false);

check('Secure con HTTPS diretto', fn () => $withTrust(null, fn () => Cookie::options(1, '/', true, $https)['secure'] === true));
check('niente Secure su HTTP', fn () => $withTrust(null, fn () => Cookie::options(1, '/', true, $http)['secure'] === false));
check('HTTPS=off non è HTTPS', fn () => $withTrust(null, fn () => !Cookie::isSecure(['HTTPS' => 'off'])));

check('dietro proxy senza APP_TRUST_PROXY: X-Forwarded-Proto ignorato', fn () =>
    $withTrust(null, fn () => Cookie::isSecure($proxied) === false)
);
check('APP_TRUST_PROXY=false: X-Forwarded-Proto ignorato', fn () =>
    $withTrust('false', fn () => Cookie::isSecure($proxied) === false)
);
check('APP_TRUST_PROXY=true: X-Forwarded-Proto https → Secure', fn () =>
    $withTrust('true', fn () => Cookie::isSecure($proxied) === true)
);
check('APP_TRUST_PROXY=true: X-Forwarded-Proto http → niente Secure', fn () =>
    $withTrust('true', fn () => Cookie::isSecure(['HTTP_X_FORWARDED_PROTO' => 'http']) === false)
);
check('APP_TRUST_PROXY=true: conta il primo valore della catena', fn () =>
    $withTrust('1', fn () => Cookie::isSecure(['HTTP_X_FORWARDED_PROTO' => 'https, http']) === true)
);

check('sessione: lifetime, SameSite=Lax, HttpOnly, Secure su HTTPS', function () use ($withTrust, $https) {
    $o = $withTrust(null, fn () => Cookie::sessionOptions(14400, $https));

    return $o === ['lifetime' => 14400, 'path' => '/', 'domain' => '', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax'];
});

$root = dirname(__DIR__, 2);

check('ini.php imposta il cookie di sessione con Cookie::sessionOptions()', fn () =>
    str_contains((string) file_get_contents($root.'/app/config/app/ini.php'), 'session_set_cookie_params(\Wonder\Http\Cookie::sessionOptions(14400))')
);

check('visitor_id passa da Cookie::set() (HttpOnly, Lax, path /)', function () use ($root) {
    $src = (string) file_get_contents($root.'/app/bootstrap/frontend.php');

    return str_contains($src, "\\Wonder\\Http\\Cookie::set('visitor_id', \$VISITOR_ID,") && !str_contains($src, 'setcookie(');
});

check('remember-me passa da Cookie::set()/clear()', function () use ($root) {
    $src = (string) file_get_contents($root.'/class/Auth/RememberMe.php');

    return str_contains($src, '\Wonder\Http\Cookie::set($name, $value, $expiresAt)') && str_contains($src, '\Wonder\Http\Cookie::clear($name)');
});

check('nessun setcookie() diretto fuori da Cookie.php', function () use ($root) {
    $hits = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app'));

    foreach ([$it, new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/class'))] as $files) {
        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')
                && !str_ends_with($file->getPathname(), 'class/Http/Cookie.php')
                && preg_match('/\bsetcookie\s*\(/', (string) file_get_contents($file->getPathname()))) {
                $hits[] = $file->getPathname();
            }
        }
    }

    return $hits === [];
});

// Header reali con il server integrato di PHP.
$port = 18000 + random_int(0, 999);
$proc = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:'.$port, __DIR__.'/fixtures/cookie-server.php'],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes
);

$fetch = static function (array $headers = [], string $query = '') use ($port): array {
    $context = stream_context_create(['http' => ['header' => implode("\r\n", $headers), 'ignore_errors' => true]]);

    for ($i = 0; $i < 50; $i++) {
        $stream = @fopen('http://127.0.0.1:'.$port.'/'.$query, 'r', false, $context);

        if ($stream !== false) {
            $headers = stream_get_meta_data($stream)['wrapper_data'] ?? [];
            fclose($stream);

            return array_values(array_filter($headers, fn ($h) => stripos($h, 'Set-Cookie:') === 0));
        }

        usleep(100000);
    }

    return [];
};

$plain = $fetch();
$forwarded = $fetch(['X-Forwarded-Proto: https']);
$trusted = $fetch(['X-Forwarded-Proto: https'], '?trust=true');
proc_terminate($proc);

check('server: tre cookie (sessione, remember, visitor_id) con SameSite=Lax e HttpOnly', function () use ($plain) {
    if (count($plain) !== 3) {
        return false;
    }

    foreach ($plain as $h) {
        if (stripos($h, 'SameSite=Lax') === false || stripos($h, 'HttpOnly') === false || stripos($h, 'path=/') === false) {
            return false;
        }
    }

    return true;
});

check('server su HTTP: nessun cookie Secure', fn () => $plain !== [] && array_filter($plain, fn ($h) => stripos($h, '; secure') !== false) === []);
check('server con X-Forwarded-Proto non fidato: nessun cookie Secure', fn () => $forwarded !== [] && array_filter($forwarded, fn ($h) => stripos($h, '; secure') !== false) === []);
check('server con X-Forwarded-Proto e APP_TRUST_PROXY: tutti Secure', fn () => count($trusted) === 3 && count(array_filter($trusted, fn ($h) => stripos($h, '; secure') !== false)) === 3);

summary();
