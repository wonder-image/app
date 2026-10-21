<?php
/** php tests/Plugin/GeoPlugin/IPInfoClientIpTest.php */
declare(strict_types=1);

require __DIR__.'/../../../vendor/autoload.php';
require __DIR__.'/../../harness.php';

use Wonder\Plugin\GeoPlugin\IPInfo;

// Senza IP risolto searchData() non chiama la rete: i casi restano offline.
$env = static function (bool $trust): void {
    $_ENV['APP_TRUST_PROXY'] = $trust ? 'true' : 'false';
};

check('senza REMOTE_ADDR e proxy non fidato: X-Forwarded-For ignorato, IP null', function () use ($env) {
    $env(false);
    $_SERVER = ['HTTP_X_FORWARDED_FOR' => '203.0.113.9'];

    return (new IPInfo())->IP === null;
});
check('senza REMOTE_ADDR e proxy non fidato: Client-IP ignorato, IP null', function () use ($env) {
    $env(false);
    $_SERVER = ['HTTP_CLIENT_IP' => '203.0.113.9'];

    return (new IPInfo())->IP === null;
});
check('proxy fidato con X-Forwarded-For non valido e senza REMOTE_ADDR: IP null', function () use ($env) {
    $env(true);
    $_SERVER = ['HTTP_X_FORWARDED_FOR' => '203.0.113.9, non-un-ip'];

    return (new IPInfo())->IP === null;
});

$src = (string) file_get_contents(__DIR__.'/../../../class/Plugin/GeoPlugin/IPInfo.php');
check('sorgente: usa ClientIp::resolve, niente header letti a mano', fn () =>
    str_contains($src, 'ClientIp::resolve(') && !str_contains($src, 'HTTP_X_FORWARDED_FOR') && !str_contains($src, 'HTTP_CLIENT_IP'));

summary();
