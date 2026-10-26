<?php
/** php tests/Plugin/GeoPlugin/GeoCountryTest.php — Geo::country() pigro, session.php senza lookup. */
declare(strict_types=1);

require __DIR__.'/../../../vendor/autoload.php';
require __DIR__.'/../../harness.php';

use Wonder\Plugin\GeoPlugin\Geo;
use Wonder\Plugin\GeoPlugin\IPInfo;

$_ENV['APP_TRUST_PROXY'] = 'false';
$root = __DIR__.'/../../..';

$fresh = static function (array $server = []): void {
    Geo::reset();
    $_SESSION = [];
    $_SERVER = $server;
};

echo "Geo::country\n";

check('paese già in sessione: lo restituisce senza ricalcolarlo', function () use ($fresh) {
    $fresh(['REMOTE_ADDR' => '203.0.113.9']);
    $_SESSION['system_cache']['country'] = 'FR';

    return Geo::country() === 'FR';
});

check('prima chiamata con IP locale: IT, salvato nella chiave di sessione', function () use ($fresh) {
    $fresh(['REMOTE_ADDR' => '127.0.0.1']);

    return Geo::country() === 'IT' && ($_SESSION['system_cache']['country'] ?? null) === 'IT';
});

check('chiamate successive rileggono la sessione', function () use ($fresh) {
    $fresh(['REMOTE_ADDR' => '127.0.0.1']);
    Geo::country();
    $_SESSION['system_cache']['country'] = 'ES';

    return Geo::country() === 'ES';
});

check('senza IP (CLI): IT', function () use ($fresh) {
    $fresh();

    return Geo::country() === 'IT';
});

$memo = new ReflectionProperty(Geo::class, 'memo');

check('senza sessione: usa il valore in memoria', function () use ($fresh, $memo) {
    $fresh(['REMOTE_ADDR' => '127.0.0.1']);
    unset($_SESSION);
    $memo->setValue(null, 'DE');
    $country = Geo::country();
    $_SESSION = [];

    return $country === 'DE';
});

check('senza sessione e senza memo: calcola e ricorda per la richiesta', function () use ($fresh, $memo) {
    $fresh(['REMOTE_ADDR' => '127.0.0.1']);
    unset($_SESSION);
    $country = Geo::country();
    $memoized = $memo->getValue();
    $noSession = !isset($_SESSION);
    $_SESSION = [];

    return $country === 'IT' && $memoized === 'IT' && $noSession;
});

check('con la sessione il memo non conta: sessione vuota, si ricalcola', function () use ($fresh, $memo) {
    $fresh(['REMOTE_ADDR' => '127.0.0.1']);
    $memo->setValue(null, 'DE');

    return Geo::country() === 'IT' && ($_SESSION['system_cache']['country'] ?? null) === 'IT';
});

echo "IPInfo e IP non pubblici\n";

check('privati, locali e riservati non sono pubblici', fn () =>
    !IPInfo::isPublicIp(null) && !IPInfo::isPublicIp('') && !IPInfo::isPublicIp('127.0.0.1')
    && !IPInfo::isPublicIp('::1') && !IPInfo::isPublicIp('10.0.0.1') && !IPInfo::isPublicIp('172.16.5.4')
    && !IPInfo::isPublicIp('192.168.1.10') && !IPInfo::isPublicIp('169.254.1.1') && !IPInfo::isPublicIp('fd00::1')
    && !IPInfo::isPublicIp('non-un-ip'));

check('IP pubblici sì', fn () => IPInfo::isPublicIp('8.8.8.8') && IPInfo::isPublicIp('2001:4860:4860::8888'));

check('IPv4 mappato (dual-stack): pubblico se lo è l\'IPv4', fn () =>
    IPInfo::isPublicIp('::ffff:8.8.8.8') && IPInfo::isPublicIp('::FFFF:203.0.114.9')
    && !IPInfo::isPublicIp('::ffff:10.0.0.1') && !IPInfo::isPublicIp('::ffff:127.0.0.1')
    && !IPInfo::isPublicIp('::ffff:192.168.1.10'));

check('normalizeIp: toglie ::ffff: solo davanti a un IPv4 valido', fn () =>
    IPInfo::normalizeIp('::ffff:203.0.113.9') === '203.0.113.9'
    && IPInfo::normalizeIp('::FFFF:8.8.8.8') === '8.8.8.8'
    && IPInfo::normalizeIp('::ffff:abcd') === '::ffff:abcd'
    && IPInfo::normalizeIp('2001:4860:4860::8888') === '2001:4860:4860::8888'
    && IPInfo::normalizeIp('8.8.8.8') === '8.8.8.8'
    && IPInfo::normalizeIp(null) === null);

check('IPv4 mappato privato: niente provider, IP normalizzato per i provider', function () {
    $start = microtime(true);
    $info = new IPInfo('::ffff:10.0.0.1');

    return $info->Country() === 'IT' && $info->IP === '10.0.0.1' && microtime(true) - $start < 0.5;
});

check('countryCode: solo due lettere, in maiuscolo', fn () =>
    IPInfo::countryCode('FR') === 'FR' && IPInfo::countryCode(' it ') === 'IT'
    && IPInfo::countryCode('') === null && IPInfo::countryCode(null) === null
    && IPInfo::countryCode('ITA') === null && IPInfo::countryCode('1T') === null
    && IPInfo::countryCode(['IT']) === null);

check('IP privati: dati di default, nessuna attesa di rete', function () {
    $start = microtime(true);
    $ok = true;
    foreach (['127.0.0.1', '::1', '10.0.0.1', '192.168.1.10'] as $ip) {
        $ok = $ok && (new IPInfo($ip))->Country() === 'IT';
    }

    return $ok && microtime(true) - $start < 0.5;
});

$ipinfo = (string) file_get_contents($root.'/class/Plugin/GeoPlugin/IPInfo.php');
check('sorgente: searchData() passa da isPublicIp()', fn () => str_contains($ipinfo, 'if (self::isPublicIp($this->IP))'));
check('sorgente: i tre provider validano il codice paese prima di usarlo', fn () =>
    substr_count($ipinfo, '($code = self::countryCode($this->result[') === 3
    && substr_count($ipinfo, "'country_code' => \$code") === 3
    && !str_contains($ipinfo, "\$this->data['country_code']\n"));

echo "session.php e lettori\n";

check('sessione nuova: niente paese calcolato, cache geo pronta', function () use ($fresh, $root) {
    $fresh(['REMOTE_ADDR' => '8.8.8.8']);
    require $root.'/app/config/app/session.php';

    return isset($_SESSION['system_cache']) && !array_key_exists('country', $_SESSION['system_cache'])
        && ($_SESSION['system_cache']['geo'] ?? null) === [];
});

$session = (string) file_get_contents($root.'/app/config/app/session.php');
check('sorgente: session.php non usa IPInfo', fn () => !str_contains($session, 'new Wonder\\Plugin\\GeoPlugin\\IPInfo') && !str_contains($session, '->Country()'));

$redirector = (string) file_get_contents($root.'/class/Localization/LanguageRedirector.php');
check('sorgente: LanguageRedirector usa Geo::country() solo fuori dai bot', fn () =>
    preg_match('/if \(!\$this->isBot\(\)\) \{\s*(\/\/[^\n]*\n\s*)?\$country \?\?= Geo::country\(\);/', $redirector) === 1
    && !str_contains($redirector, "system_cache"));

$address = (string) file_get_contents($root.'/class/Plugin/Custom/Address/Address.php');
check('sorgente: Address usa Geo::country()', fn () =>
    str_contains($address, 'Geo::country()') && !str_contains($address, "system_cache"));

summary();
