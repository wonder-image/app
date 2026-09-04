<?php
/** php tests/App/DebugTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Debug;

/** Prepara un ambiente deterministico e azzera la memoizzazione. */
function withEnv(array $env, array $server): void {
    foreach (['APP_DEBUG'] as $k) { unset($_ENV[$k], $_SERVER[$k]); }
    foreach (['REMOTE_ADDR', 'SERVER_NAME'] as $k) { unset($_SERVER[$k]); }
    foreach ($env as $k => $v) { $_ENV[$k] = $v; }
    foreach ($server as $k => $v) { $_SERVER[$k] = $v; }
    Debug::reset();
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

check('nessun APP_DEBUG + REMOTE_ADDR 127.0.0.1 abilita', function () {
    withEnv([], ['REMOTE_ADDR' => '127.0.0.1']);
    return Debug::enabled() === true;
});

check('nessun APP_DEBUG + SERVER_NAME localhost abilita', function () {
    withEnv([], ['SERVER_NAME' => 'localhost']);
    return Debug::enabled() === true;
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

summary();
