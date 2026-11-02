<?php
/** php tests/Http/TrustedProxiesTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Http\ClientIp;
use Wonder\Http\Cookie;
use Wonder\Http\TrustedProxies;
use Wonder\Http\UrlParser;

$withEnv = static function (?string $trust, ?string $list, callable $fn) {
    foreach (['APP_TRUST_PROXY' => $trust, 'APP_TRUSTED_PROXIES' => $list] as $key => $value) {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
        if ($value !== null) {
            $_ENV[$key] = $value;
        }
    }

    try {
        return $fn();
    } finally {
        unset($_ENV['APP_TRUST_PROXY'], $_ENV['APP_TRUSTED_PROXIES']);
    }
};

$proxies = '10.0.0.0/8, 2001:db8:cafe::/48, 192.0.2.7';
$spoofed = [
    'REMOTE_ADDR' => '198.51.100.66',
    'HTTP_X_FORWARDED_FOR' => '6.6.6.6',
    'HTTP_X_FORWARDED_PROTO' => 'https',
    'HTTP_X_FORWARDED_SCHEME' => 'https',
    'HTTP_X_FORWARDED_SSL' => 'on',
];

check('accesso diretto all\'origin con la lista: header del client ignorati, anche con APP_TRUST_PROXY', fn () => $withEnv('true', $proxies, fn () =>
    ClientIp::resolve($spoofed) === '198.51.100.66'
    && !Cookie::isSecure($spoofed)
    && !UrlParser::isHttpsRequest($spoofed)
    && UrlParser::requestIp($spoofed) === '198.51.100.66'));

check('la lista da sola attiva la fiducia per i proxy elencati', fn () => $withEnv(null, $proxies, fn () =>
    ClientIp::resolve(['REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9']) === '203.0.113.9'
    && Cookie::isSecure(['REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_PROTO' => 'https'])
    && UrlParser::isHttpsRequest(['REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_SSL' => 'on'])));

check('catena di proxy: si saltano da destra i proxy della lista', fn () => $withEnv(null, $proxies, fn () =>
    ClientIp::resolve(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 203.0.113.9, 192.0.2.7, 10.9.9.9']) === '203.0.113.9'));

check('catena IPv6 e IPv4 mappato', fn () => $withEnv(null, $proxies, fn () =>
    ClientIp::resolve(['REMOTE_ADDR' => '2001:db8:cafe:1::2', 'HTTP_X_FORWARDED_FOR' => '2001:db8:1::9, ::ffff:10.0.0.1']) === '2001:db8:1::9'
    && ClientIp::resolve(['REMOTE_ADDR' => '::ffff:192.0.2.7', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9']) === '203.0.113.9'));

check('voce non valida, XFF assente o solo proxy: REMOTE_ADDR', fn () => $withEnv(null, $proxies, fn () =>
    ClientIp::resolve(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9, unknown, 10.0.0.6']) === '10.0.0.5'
    && ClientIp::resolve(['REMOTE_ADDR' => '10.0.0.5']) === '10.0.0.5'
    && ClientIp::resolve(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '10.0.0.6, 192.0.2.7']) === '10.0.0.5'));

check('CIDR: confini del prefisso IPv4 e IPv6', function () {
    $list = TrustedProxies::parse('172.16.0.0/12, 2001:db8:cafe::/48, 0.0.0.0/0');
    $narrow = TrustedProxies::parse('172.16.0.0/12, 2001:db8:cafe::/48');

    return TrustedProxies::contains('172.31.255.255', $narrow)
        && !TrustedProxies::contains('172.32.0.0', $narrow)
        && TrustedProxies::contains('2001:db8:cafe:ffff::1', $narrow)
        && !TrustedProxies::contains('2001:db8:caff::1', $narrow)
        && TrustedProxies::contains('8.8.8.8', $list)
        && !TrustedProxies::contains('2001:db9::1', $list);
});

check('voci non valide della lista ignorate, nessuna eccezione', function () {
    $list = TrustedProxies::parse(' , ciao, 10.0.0.0/33, 10.0.0.0/-1, 10.0.0.0/x, ::1/129, 192.0.2.7 ,');

    return count($list) === 1 && TrustedProxies::contains('192.0.2.7', $list) && !TrustedProxies::contains('ciao', $list);
});

check('lista vuota o solo voci non valide: vale APP_TRUST_PROXY come prima', fn () =>
    $withEnv('true', 'nessuno', fn () =>
        ClientIp::resolve(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 203.0.113.77']) === '203.0.113.77'
        && UrlParser::isHttpsRequest(['HTTP_X_FORWARDED_PROTO' => 'https']))
    && $withEnv(null, '', fn () =>
        ClientIp::resolve(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '203.0.113.77']) === '10.0.0.5'
        && !Cookie::isSecure(['HTTP_X_FORWARDED_PROTO' => 'https'])));

summary();
