<?php
/** php tests/Http/ErrorStatusTest.php — codici applicativi → codice HTTP. */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Api\ErrorStatus;

echo "ErrorStatus\n";

$root = dirname(__DIR__, 2);

check('617 e 618 → 422', fn () => ErrorStatus::http(617) === 422 && ErrorStatus::http(618) === 422);
check('codici HTTP di errore restano', fn () => ErrorStatus::http(400) === 400 && ErrorStatus::http(404) === 404 && ErrorStatus::http(500) === 500 && ErrorStatus::http(599) === 599);
check('fuori da 400–599 → 500', fn () => ErrorStatus::http(0) === 500 && ErrorStatus::http(200) === 500 && ErrorStatus::http(399) === 500 && ErrorStatus::http(700) === 500 && ErrorStatus::http(-1) === 500);

check('Handler::sendError passa da ErrorStatus e tiene status nel corpo', function () use ($root) {
    $source = (string) file_get_contents($root.'/class/Api/Handler.php');

    return str_contains($source, 'http_response_code(ErrorStatus::http($status));') && !str_contains($source, 'http_response_code($status);');
});

check('API resource: tutte e due le catch passano da ErrorStatus', function () use ($root) {
    $source = (string) file_get_contents($root.'/app/http/api/resource/index.php');

    return substr_count($source, '\Wonder\Api\ErrorStatus::http(') === 2;
});

check('login: il 618 della guardia diventa l\'avviso, non un errore', function () use ($root) {
    $source = (string) file_get_contents($root.'/class/Auth/Frontend/AuthController.php');

    return str_contains($source, 'if (!RecaptchaGuard::isFailure($exception))') && str_contains($source, '$ALERT = $exception->getCode();');
});

summary();
