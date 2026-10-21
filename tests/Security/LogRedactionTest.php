<?php
/** php tests/Security/LogRedactionTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\LogRedactor;
use Wonder\App\Logger;

check('uri: token, t, key, password, email, _csrf oscurati; il resto resta', function () {
    $u = LogRedactor::uri('/account/verify/?token=abc&t=1&KEY=k&password=p&email=a%40b.it&_csrf=x&page=2#top');
    return $u === '/account/verify/?token=***&t=***&KEY=***&password=***&email=***&_csrf=***&page=2#top';
});

check('uri: nomi con quadre e nomi codificati', function () {
    return LogRedactor::uri('/x?email[]=a@b.it&%74oken=z&q=ok') === '/x?email[]=***&%74oken=***&q=ok';
});

check('uri senza query invariato', function () {
    return LogRedactor::uri('/contatti/') === '/contatti/' && LogRedactor::uri('') === '';
});

check('identifier: email mascherata, altro testo ridotto alla prima lettera', function () {
    return LogRedactor::identifier('mario.rossi@example.com') === 'm***@example.com'
        && LogRedactor::identifier('mario') === 'm***'
        && LogRedactor::identifier('  ') === '';
});

/** Root temporanea per il Logger, ripulita dopo la verifica. */
function withLogRoot(callable $fn): mixed {
    $dir = sys_get_temp_dir() . '/wi-log-' . bin2hex(random_bytes(4));
    mkdir($dir);
    $GLOBALS['ROOT'] = $dir;
    try {
        return $fn($dir);
    } finally {
        unset($GLOBALS['ROOT']);
        exec('rm -rf ' . escapeshellarg($dir));
    }
}

check('Logger: scrive in ROOT/storage/logs con cartella 0750 e file 0640', function () {
    return withLogRoot(function (string $dir) {
        $prev = umask(0);
        try {
            Logger::append('error/test', '{"ok":true}');
        } finally {
            umask($prev);
        }
        $file = $dir . '/storage/logs/error/test.log';
        clearstatcache();
        return is_file($file)
            && (fileperms($dir . '/storage/logs/error') & 0777) === 0750
            && (fileperms($file) & 0777) === 0640
            && trim((string) file_get_contents($file)) === '{"ok":true}';
    });
});

check('Logger: la root non dipende dalla cartella corrente', function () {
    return withLogRoot(function (string $dir) {
        $prev = getcwd();
        chdir(sys_get_temp_dir());
        try {
            $root = Logger::root();
        } finally {
            chdir((string) $prev);
        }
        return $root === $dir;
    });
});

check('Logger: cartella non scrivibile, nessuna eccezione', function () {
    $dir = sys_get_temp_dir() . '/wi-log-ro-' . bin2hex(random_bytes(4));
    mkdir($dir, 0500);
    $GLOBALS['ROOT'] = $dir;
    $prevLog = ini_set('error_log', '/dev/null');
    try {
        Logger::append('error/test', 'riga');
        $ok = !is_dir($dir . '/storage');
    } finally {
        ini_set('error_log', (string) $prevLog);
        unset($GLOBALS['ROOT']);
        chmod($dir, 0700);
        exec('rm -rf ' . escapeshellarg($dir));
    }
    return $ok;
});

check('Logger::log: request_id coerente e URI non nel messaggio', function () {
    return withLogRoot(function (string $dir) {
        $_SERVER['REQUEST_ID'] = 'log-9';
        Logger::log(new RuntimeException('boom'), 'test', 'redaction', 'ERROR', 'error/r', [
            'uri' => LogRedactor::uri('/x?token=segreto'),
        ], false);
        unset($_SERVER['REQUEST_ID']);
        $raw = (string) file_get_contents($dir . '/storage/logs/error/r.log');
        $line = json_decode(trim($raw), true);
        return ($line['request_id'] ?? '') === 'log-9' && !str_contains($raw, 'segreto')
            && ($line['context']['uri'] ?? '') === '/x?token=***';
    });
});

summary();
