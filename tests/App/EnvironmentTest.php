<?php
/** php tests/App/EnvironmentTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Environment;

function withAppEnv(?string $value): void {
    unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
    putenv('APP_ENV');

    if ($value !== null) {
        $_ENV['APP_ENV'] = $value;
    }

    Environment::reset();
}

/** Esegue $fn e ritorna il messaggio di EnvironmentException, o null se non lancia. */
function environmentError(callable $fn): ?string {
    try {
        $fn();
    } catch (\Wonder\App\EnvironmentException $e) {
        return $e->getMessage();
    }

    return null;
}

check('senza APP_ENV lancia EnvironmentException (niente ripiego su production)', function () {
    withAppEnv(null);
    $message = environmentError(fn () => Environment::current());

    return $message !== null
        && str_contains($message, 'assente')
        && str_contains($message, 'local, staging, production')
        && str_contains($message, 'APP_ENV')
        && str_contains($message, '.env');
});

check('APP_ENV vuoto lancia come se mancasse', function () {
    withAppEnv('   ');
    return environmentError(fn () => Environment::isLocal()) !== null;
});

check('assertConfigured() lancia senza APP_ENV e ritorna il valore se valido', function () {
    withAppEnv(null);
    $lancia = environmentError(fn () => Environment::assertConfigured()) !== null;
    withAppEnv('staging');
    return $lancia && Environment::assertConfigured() === 'staging';
});

check('dopo un errore nulla è memorizzato: con APP_ENV valido risponde', function () {
    withAppEnv(null);
    environmentError(fn () => Environment::current());
    $_ENV['APP_ENV'] = 'production';
    return Environment::current() === 'production';
});

check('APP_ENV=local', function () {
    withAppEnv('local');
    return Environment::current() === 'local' && Environment::isLocal() && !Environment::isProduction();
});

check('maiuscole e spazi normalizzati', function () {
    withAppEnv('  LOCAL ');
    return Environment::isLocal();
});

check('APP_ENV=staging', function () {
    withAppEnv('staging');
    return Environment::current() === 'staging' && Environment::isStaging()
        && !Environment::isLocal() && !Environment::isProduction();
});

check('APP_ENV=production: isStaging() è falso', function () {
    withAppEnv('production');
    return Environment::isProduction() && !Environment::isStaging() && !Environment::isLocal();
});

check('valore non valido (prod) lancia e il messaggio riporta il valore trovato', function () {
    withAppEnv('prod');
    $message = environmentError(fn () => Environment::current());

    return $message !== null
        && str_contains($message, '"prod"')
        && str_contains($message, 'local, staging, production');
});

check('letto anche da $_SERVER', function () {
    withAppEnv(null);
    $_SERVER['APP_ENV'] = 'local';
    Environment::reset();
    return Environment::isLocal();
});

check('reset() rilegge il valore', function () {
    withAppEnv('local');
    $first = Environment::isLocal();
    $_ENV['APP_ENV'] = 'production';
    Environment::reset();
    return $first === true && Environment::isProduction();
});

check('il .env del sito viene letto anche se nessuno lo ha ancora caricato', function () {
    // Simula una richiesta web che chiede l'ambiente prima del database.
    $root = sys_get_temp_dir().'/wi-env-'.bin2hex(random_bytes(4));
    mkdir($root);
    file_put_contents($root.'/.env', "APP_ENV=local\n");

    unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
    putenv('APP_ENV');
    \Wonder\App\LegacyGlobals::share(['ROOT' => $root]);
    \Wonder\App\Credentials::resetEnv();
    Environment::reset();

    $letto = Environment::current();

    unlink($root.'/.env');
    rmdir($root);
    unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
    putenv('APP_ENV');
    \Wonder\App\Credentials::resetEnv();
    Environment::reset();

    return $letto === 'local';
});

/** Esegue $fn con `error_log` su un file temporaneo e ritorna le righe scritte. */
function captureErrorLog(callable $fn): string {
    $file = sys_get_temp_dir().'/wi-errlog-'.bin2hex(random_bytes(4));
    $previous = ini_get('error_log');
    ini_set('error_log', $file);

    try {
        $fn();
    } finally {
        ini_set('error_log', (string) $previous);
    }

    $log = is_file($file) ? (string) file_get_contents($file) : '';
    @unlink($file);

    return $log;
}

function withEncryptionKey(?string $value): void {
    unset($_ENV['APP_ENCRYPTION_KEY'], $_ENV['APP_ENCRYPTION_KEY_PREVIOUS']);

    if ($value !== null) {
        $_ENV['APP_ENCRYPTION_KEY'] = $value;
    }
}

check('chiave di cifratura assente in production via web: avviso nel log, nessun blocco', function () {
    withAppEnv('production');
    withEncryptionKey(null);
    $terminated = false;

    $log = captureErrorLog(function () use (&$terminated) {
        Environment::bootGuard(function () use (&$terminated) { $terminated = true; }, 'fpm-fcgi');
    });

    return !$terminated
        && str_contains($log, 'APP_ENCRYPTION_KEY assente o non valida')
        && str_contains($log, 'enc:v1:');
});

check('l\'avviso si scrive una volta per processo', function () {
    withAppEnv('production');
    withEncryptionKey(null);

    $first = captureErrorLog(fn () => Environment::bootGuard(fn () => null, 'fpm-fcgi'));
    $second = captureErrorLog(fn () => Environment::bootGuard(fn () => null, 'fpm-fcgi'));

    return $first !== '' && $second === '';
});

check('chiave non valida (non 64 esadecimali) vale come assente, senza scrivere il valore', function () {
    withAppEnv('staging');
    withEncryptionKey('troppo-corta-segreto-finto');

    $log = captureErrorLog(fn () => Environment::bootGuard(fn () => null, 'fpm-fcgi'));

    return str_contains($log, 'assente o non valida') && !str_contains($log, 'troppo-corta-segreto-finto');
});

check('chiave valida: nessun avviso', function () {
    withAppEnv('production');
    withEncryptionKey(str_repeat('ab', 32));

    $log = captureErrorLog(fn () => Environment::bootGuard(fn () => null, 'fpm-fcgi'));
    withEncryptionKey(null);

    return $log === '';
});

check('in local e da riga di comando non avvisa', function () {
    withEncryptionKey(null);
    withAppEnv('local');
    $local = captureErrorLog(fn () => Environment::bootGuard(fn () => null, 'fpm-fcgi'));

    withAppEnv('production');
    $cli = captureErrorLog(fn () => Environment::bootGuard(fn () => null, 'cli'));

    return $local === '' && $cli === '';
});

check('con APP_ENV non valido resta l\'errore di prima e non si avvisa sulla chiave', function () {
    withAppEnv('prod');
    withEncryptionKey(null);
    $terminated = null;

    $log = captureErrorLog(function () use (&$terminated) {
        Environment::bootGuard(function (int $code) use (&$terminated) { $terminated = $code; }, 'cli');
    });

    return $terminated === 1 && str_contains($log, 'APP_ENV non valido') && !str_contains($log, 'APP_ENCRYPTION_KEY');
});

withEncryptionKey(null);
withAppEnv('production');

summary();
