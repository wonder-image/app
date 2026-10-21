<?php
/** php tests/App/DebugTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Debug;
use Wonder\App\Environment;

/** Prepara un ambiente deterministico e azzera la memoizzazione. */
function withEnv(array $env, array $server): void {
    foreach (['APP_DEBUG', 'APP_ENV'] as $k) { unset($_ENV[$k], $_SERVER[$k]); putenv($k); }
    foreach (['REMOTE_ADDR', 'SERVER_NAME'] as $k) { unset($_SERVER[$k]); }
    foreach ($env as $k => $v) { $_ENV[$k] = $v; }
    foreach ($server as $k => $v) { $_SERVER[$k] = $v; }
    Debug::reset();
    Environment::reset();
}

check('APP_DEBUG=1 abilita', function () {
    withEnv(['APP_DEBUG' => '1'], []);
    return Debug::enabled() === true;
});

check('APP_DEBUG=true abilita (case-insensitive)', function () {
    withEnv(['APP_DEBUG' => 'TRUE'], []);
    return Debug::enabled() === true;
});

check('APP_DEBUG=on/yes abilitano', function () {
    withEnv(['APP_DEBUG' => 'on'], []);
    $a = Debug::enabled();
    withEnv(['APP_DEBUG' => 'yes'], []);
    $b = Debug::enabled();
    return $a === true && $b === true;
});

check('APP_DEBUG bool true abilita', function () {
    withEnv(['APP_DEBUG' => true], []);
    return Debug::enabled() === true;
});

check('APP_DEBUG=0 senza localhost disabilita', function () {
    withEnv(['APP_DEBUG' => '0'], ['REMOTE_ADDR' => '203.0.113.5', 'SERVER_NAME' => 'example.test']);
    return Debug::enabled() === false;
});

check('nessun APP_DEBUG + REMOTE_ADDR 127.0.0.1 non abilita (niente fallback di rete)', function () {
    withEnv([], ['REMOTE_ADDR' => '127.0.0.1']);
    $a = Debug::enabled();
    withEnv([], ['REMOTE_ADDR' => '::1']);
    return $a === false && Debug::enabled() === false;
});

check('nessun APP_DEBUG + SERVER_NAME localhost non abilita', function () {
    withEnv([], ['SERVER_NAME' => 'localhost']);
    return Debug::enabled() === false;
});

check('nessun APP_DEBUG + APP_ENV=local abilita (caso ecommerce-site)', function () {
    withEnv(['APP_ENV' => 'local'], ['REMOTE_ADDR' => '203.0.113.5']);
    $a = Debug::enabled();
    withEnv(['APP_ENV' => 'LOCAL'], []);
    return $a === true && Debug::enabled() === true;
});

check('nessun APP_DEBUG + APP_ENV=production o assente disabilita', function () {
    withEnv(['APP_ENV' => 'production'], ['REMOTE_ADDR' => '127.0.0.1']);
    $a = Debug::enabled();
    withEnv([], []);
    return $a === false && Debug::enabled() === false;
});

check('APP_DEBUG=false esplicito vince su APP_ENV=local', function () {
    withEnv(['APP_DEBUG' => 'false', 'APP_ENV' => 'local'], []);
    $a = Debug::enabled();
    withEnv(['APP_DEBUG' => false, 'APP_ENV' => 'local'], []);
    return $a === false && Debug::enabled() === false;
});

check('APP_DEBUG vuoto equivale ad assente', function () {
    withEnv(['APP_DEBUG' => '', 'APP_ENV' => 'local'], []);
    $a = Debug::enabled();
    withEnv(['APP_DEBUG' => '', 'APP_ENV' => 'production'], []);
    return $a === true && Debug::enabled() === false;
});

check("reset() rilegge l'ambiente", function () {
    withEnv(['APP_DEBUG' => '1'], []);
    $first = Debug::enabled();
    $_ENV['APP_DEBUG'] = '0';
    unset($_SERVER['REMOTE_ADDR'], $_SERVER['SERVER_NAME']);
    Debug::reset();
    $second = Debug::enabled();
    return $first === true && $second === false;
});

check('.env di ecommerce-site (letto, non caricato): Debug::enabled() === true', function () {
    $file = '/Users/andreamarinoni/Developer/boilerplates/ecommerce-site/.env';
    if (!is_file($file)) { echo "    (saltato: .env assente)\n"; return true; }
    $vars = \Dotenv\Dotenv::parse((string) file_get_contents($file));
    $env = array_intersect_key($vars, ['APP_DEBUG' => 1, 'APP_ENV' => 1]);
    withEnv($env, ['REMOTE_ADDR' => '127.0.0.1']);
    return ($vars['APP_ENV'] ?? null) === 'local' && !isset($vars['APP_DEBUG']) && Debug::enabled() === true;
});

summary();
