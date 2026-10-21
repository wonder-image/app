<?php
/** php tests/Auth/RememberMeIpTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Auth\RememberMe;

// Output in buffer: i cookie si impostano solo prima degli header.
ob_start();
session_start();

$GLOBALS['tInsert'] = [];
$GLOBALS['tModify'] = [];
$GLOBALS['tRow'] = null;

function sqlInsert($table, $values): object
{
    $GLOBALS['tInsert'][] = [$table, $values];

    return (object) ['success' => true, 'insert_id' => 1];
}
function sqlModify($table, $values, $column = null, $value = null): object
{
    $GLOBALS['tModify'][] = [$table, $values];

    return (object) ['success' => true];
}
function sqlSelect($table, $cond = null, $limit = null): object
{
    return $GLOBALS['tRow'] === null
        ? (object) ['exists' => false, 'row' => []]
        : (object) ['exists' => true, 'row' => $GLOBALS['tRow']];
}

// Dietro proxy fidato: REMOTE_ADDR è il proxy, l'ultima voce XFF il client.
$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 203.0.113.9';
$_ENV['APP_TRUST_PROXY'] = 'true';

$rememberIp = static function (): ?string {
    foreach ($GLOBALS['tInsert'] as [$table, $values]) {
        if ($table === 'auth_remember') {
            return $values['ip'] ?? null;
        }
    }

    return null;
};

check('set: l\'ip del token è quello di ClientIp, non REMOTE_ADDR', function () use ($rememberIp) {
    $GLOBALS['tInsert'] = [];
    RememberMe::set(7, 'frontend');

    return $rememberIp() === '203.0.113.9';
});

check('tryLogin: la rotazione scrive l\'ip di ClientIp', function () {
    $validator = str_repeat('ab', 32);
    $GLOBALS['tRow'] = [
        'id' => 3, 'user_id' => 7, 'area' => 'frontend',
        'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        'token_hash' => hash('sha256', $validator),
    ];
    $_COOKIE['remember_frontend'] = 'sel:'.$validator;
    $GLOBALS['tModify'] = [];
    $userId = RememberMe::tryLogin('frontend');
    $ip = null;
    foreach ($GLOBALS['tModify'] as [$table, $values]) {
        if ($table === 'auth_remember' && isset($values['token_hash'])) {
            $ip = $values['ip'] ?? null;
        }
    }

    return $userId === 7 && $ip === '203.0.113.9';
});

check('senza APP_TRUST_PROXY l\'ip resta REMOTE_ADDR', function () use ($rememberIp) {
    $_ENV['APP_TRUST_PROXY'] = 'false';
    $GLOBALS['tInsert'] = [];
    RememberMe::set(7, 'frontend');
    $_ENV['APP_TRUST_PROXY'] = 'true';

    return $rememberIp() === '10.0.0.1';
});

summary();
