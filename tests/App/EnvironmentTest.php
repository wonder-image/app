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

check('senza APP_ENV vale production', function () {
    withAppEnv(null);
    return Environment::current() === 'production' && Environment::isProduction() && !Environment::isLocal();
});

check('APP_ENV=local', function () {
    withAppEnv('local');
    return Environment::current() === 'local' && Environment::isLocal() && !Environment::isProduction();
});

check('maiuscole e spazi normalizzati', function () {
    withAppEnv('  LOCAL ');
    return Environment::isLocal();
});

check('valore non valido vale production', function () {
    withAppEnv('staging');
    return Environment::current() === 'production';
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

summary();
