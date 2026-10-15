<?php
/** php tests/OAuth/ConfigTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Environment;
use Wonder\OAuth\Config;
use Wonder\OAuth\OAuthException;

$set = static function (string $key, ?string $value): void {
    if ($value === null) {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
        return;
    }
    $_ENV[$key] = $value;
};

$keys = ['OAUTH_REGISTER_RATE_LIMIT', 'OAUTH_ACCESS_TOKEN_TTL', 'OAUTH_REFRESH_TOKEN_TTL', 'OAUTH_GRANT_MAX_AGE'];
foreach ($keys as $k) { $set($k, null); }
ini_set('error_log', '/dev/null');

check('default senza chiavi', fn () =>
    Config::registerRateLimit() === 20
    && Config::accessTokenTtl() === 3600
    && Config::refreshTokenTtl() === 2592000
    && Config::grantMaxAge() === 7776000);

check('valori validi letti dal .env', function () use ($set) {
    $set('OAUTH_ACCESS_TOKEN_TTL', '900');
    $set('OAUTH_REGISTER_RATE_LIMIT', '50');
    $ok = Config::accessTokenTtl() === 900 && Config::registerRateLimit() === 50;
    $set('OAUTH_ACCESS_TOKEN_TTL', null);
    $set('OAUTH_REGISTER_RATE_LIMIT', null);
    return $ok;
});

check('non intero o vuoto → default', function () use ($set) {
    $set('OAUTH_ACCESS_TOKEN_TTL', 'un\'ora');
    $a = Config::accessTokenTtl();
    $set('OAUTH_ACCESS_TOKEN_TTL', '');
    $b = Config::accessTokenTtl();
    $set('OAUTH_ACCESS_TOKEN_TTL', '12.5');
    $c = Config::accessTokenTtl();
    $set('OAUTH_ACCESS_TOKEN_TTL', null);
    return $a === 3600 && $b === 3600 && $c === 3600;
});

check('fuori limiti → limite più vicino', function () use ($set) {
    $set('OAUTH_ACCESS_TOKEN_TTL', '10');
    $low = Config::accessTokenTtl();
    $set('OAUTH_REFRESH_TOKEN_TTL', '999999999');
    $high = Config::refreshTokenTtl();
    $set('OAUTH_ACCESS_TOKEN_TTL', null);
    $set('OAUTH_REFRESH_TOKEN_TTL', null);
    return $low === 300 && $high === 7776000;
});

check('rate limit 0 disattiva', function () use ($set) {
    $set('OAUTH_REGISTER_RATE_LIMIT', '0');
    $ok = Config::registerRateLimit() === 0;
    $set('OAUTH_REGISTER_RATE_LIMIT', null);
    return $ok;
});

check('issuer da APP_URL senza slash finale', function () use ($set) {
    $set('APP_URL', 'https://sito.it/');
    return Config::issuer() === 'https://sito.it';
});

check('issuer http fuori dal locale → 500', function () use ($set) {
    $set('APP_URL', 'http://sito.it');
    $set('APP_ENV', 'production');
    Environment::reset();
    try { Config::issuer(); return false; }
    catch (OAuthException $e) { return $e->status === 500 && $e->error === 'server_error'; }
    finally { $set('APP_ENV', null); Environment::reset(); }
});

check('issuer http in locale ammesso', function () use ($set) {
    $set('APP_URL', 'http://sito.test');
    $set('APP_ENV', 'local');
    Environment::reset();
    $ok = Config::issuer() === 'http://sito.test';
    $set('APP_ENV', null);
    Environment::reset();
    return $ok;
});

summary();
