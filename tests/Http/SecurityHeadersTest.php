<?php
/** php tests/Http/SecurityHeadersTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Http\SecurityHeaders;

check('header base sempre presenti, Permissions-Policy con geolocation=(self)', function () {
    $h = SecurityHeaders::headers(false, false, false, false);
    return $h['X-Content-Type-Options'] === 'nosniff'
        && $h['X-Frame-Options'] === 'SAMEORIGIN'
        && $h['Content-Security-Policy'] === "frame-ancestors 'self'"
        && $h['Referrer-Policy'] === 'strict-origin-when-cross-origin'
        && $h['Permissions-Policy'] === 'camera=(), microphone=(), geolocation=(self)'
        && !isset($h['X-XSS-Protection']);
});

check('HSTS solo con APP_HSTS e HTTPS, max-age=31536000 senza preload', function () {
    $on = SecurityHeaders::headers(false, false, true, true);
    return ($on['Strict-Transport-Security'] ?? '') === 'max-age=31536000'
        && !isset(SecurityHeaders::headers(false, false, true, false)['Strict-Transport-Security'])
        && !isset(SecurityHeaders::headers(false, false, false, true)['Strict-Transport-Security']);
});

check('Cache-Control no-store, private: sempre sul backend', function () {
    return (SecurityHeaders::headers(true, false, false, false)['Cache-Control'] ?? '') === 'no-store, private'
        && (SecurityHeaders::headers(true, true, false, false)['Cache-Control'] ?? '') === 'no-store, private';
});

check('Cache-Control: frontend autenticato sì, anonimo no', function () {
    return (SecurityHeaders::headers(false, true, false, false)['Cache-Control'] ?? '') === 'no-store, private'
        && !isset(SecurityHeaders::headers(false, false, false, false)['Cache-Control']);
});

check('presentNames: confronto dei nomi case-insensitive', function () {
    $p = SecurityHeaders::presentNames(['x-frame-options: DENY', 'Content-Security-Policy: frame-ancestors \'none\'', 'bad']);
    return isset($p['x-frame-options']) && isset($p['content-security-policy']) && count($p) === 2;
});

check('APP_HSTS: true/1/on abilitano, vuoto o assente no', function () {
    $r = [];
    foreach (['true' => true, '1' => true, 'on' => true, '' => false, 'false' => false] as $v => $exp) {
        $_ENV['APP_HSTS'] = (string) $v;
        $r[] = SecurityHeaders::hstsEnabled() === $exp;
    }
    unset($_ENV['APP_HSTS']); putenv('APP_HSTS');
    return !in_array(false, $r, true) && SecurityHeaders::hstsEnabled() === false;
});

check('apply() su php -S: header emessi, DENY preesistente non sovrascritto, Cache-Control sostituito', function () {
    $dir = sys_get_temp_dir().'/wi-sech-'.bin2hex(random_bytes(4));
    mkdir($dir);
    $autoload = var_export(realpath(__DIR__.'/../../vendor/autoload.php'), true);
    file_put_contents($dir.'/index.php', "<?php require $autoload;\n"
        ."if (isset(\$_GET['oauth'])) { header('X-Frame-Options: DENY'); }\n"
        ."header('Cache-Control: no-store, no-cache, must-revalidate');\n"
        ."header('X-Powered-By: PHP/test');\n"
        ."\\Wonder\\Http\\SecurityHeaders::apply(isset(\$_GET['backend']), false); echo 'ok';");
    $port = random_int(20000, 40000);
    $proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', $dir], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    $get = function (string $q) use ($port): array {
        for ($i = 0; $i < 50; $i++) {
            $h = @get_headers('http://127.0.0.1:'.$port.'/index.php'.$q, true);
            if ($h !== false) { return array_change_key_case($h, CASE_LOWER); }
            usleep(100000);
        }
        return [];
    };
    $front = $get('');
    $oauth = $get('?oauth=1');
    $back = $get('?backend=1');
    proc_terminate($proc); proc_close($proc);
    exec('rm -rf '.escapeshellarg($dir));
    return ($front['x-frame-options'] ?? '') === 'SAMEORIGIN'
        && ($front['x-content-type-options'] ?? '') === 'nosniff'
        && !isset($front['x-powered-by'])
        && ($front['cache-control'] ?? '') === 'no-store, no-cache, must-revalidate'
        && ($oauth['x-frame-options'] ?? '') === 'DENY'
        && ($back['cache-control'] ?? '') === 'no-store, private';
});

summary();
