<?php
// Router per `php -S` usato da tests/Http/CookieTest.php: emette i cookie del core.
if (PHP_SAPI !== 'cli-server') {
    return;
}

require __DIR__.'/../../../vendor/autoload.php';

if (isset($_GET['trust'])) {
    $_ENV['APP_TRUST_PROXY'] = $_GET['trust'];
}

session_set_cookie_params(\Wonder\Http\Cookie::sessionOptions(14400));
session_start();
\Wonder\Http\Cookie::set('remember_backend', 'sel:val', time() + 3600);
\Wonder\Http\Cookie::set('visitor_id', 'abc', time() + 3600);
echo 'ok';
