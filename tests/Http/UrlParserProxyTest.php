<?php
/** php tests/Http/UrlParserProxyTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Http\UrlParser;

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

$proxied = ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 203.0.113.9'];

foreach (['HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_SCHEME' => 'https', 'HTTP_X_FORWARDED_SSL' => 'on'] as $h => $v) {
    check("senza APP_TRUST_PROXY: {$h} ignorato", fn () => $withTrust(null, fn () =>
        UrlParser::isHttpsRequest([$h => $v]) === false && UrlParser::requestScheme([$h => $v]) === 'http'));
    check("con APP_TRUST_PROXY: {$h} vale", fn () => $withTrust('true', fn () =>
        UrlParser::isHttpsRequest([$h => $v]) === true));
}

check('HTTPS diretto e porta 443 valgono sempre', fn () => $withTrust(null, fn () =>
    UrlParser::isHttpsRequest(['HTTPS' => 'on']) && UrlParser::isHttpsRequest(['SERVER_PORT' => '443'])
    && UrlParser::isHttpsRequest(['REQUEST_SCHEME' => 'https']) && !UrlParser::isHttpsRequest([])));

check('requestIp senza APP_TRUST_PROXY: REMOTE_ADDR, X-Forwarded-For ignorato', fn () => $withTrust(null, fn () =>
    UrlParser::requestIp($proxied) === '10.0.0.5'));
check('requestIp con APP_TRUST_PROXY: ultima voce di X-Forwarded-For, come ClientIp', fn () => $withTrust('true', fn () =>
    UrlParser::requestIp($proxied) === '203.0.113.9'));

check('requestHost: solo Host della richiesta, X-Forwarded-Host ignorato', fn () => $withTrust('true', fn () =>
    UrlParser::requestHost(['HTTP_HOST' => 'sito.test', 'HTTP_X_FORWARDED_HOST' => 'evil.test']) === 'sito.test'));

summary();
