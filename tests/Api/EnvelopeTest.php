<?php
/** php tests/Api/EnvelopeTest.php — la busta comune delle risposte JSON. */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Api\Envelope;
use Wonder\Http\HttpErrorResponse;

echo "Envelope\n";

$root = dirname(__DIR__, 2);

check('success: dato in response', fn () => Envelope::success(['id' => 3]) === ['success' => true, 'status' => 200, 'response' => ['id' => 3]]);
check('success: status scelto', fn () => Envelope::success('ok', 201)['status'] === 201);
check('error: messaggio in response, niente request_id', fn () => Envelope::error('No', 422) === ['success' => false, 'status' => 422, 'response' => 'No']);
check('error: messaggio vuoto → generico', fn () => Envelope::error('', 500)['response'] === HttpErrorResponse::GENERIC_MESSAGE);
check('error: request_id solo se c\'è', fn () => Envelope::error('No', 500, 'abc')['request_id'] === 'abc' && !array_key_exists('request_id', Envelope::error('No', 500, '')));

check('ApiRequest::ok e error passano dalla busta', function () use ($root) {
    $source = (string) file_get_contents($root.'/class/App/Support/ApiRequest.php');

    return str_contains($source, 'Envelope::success($response, $status)') && str_contains($source, 'Envelope::error($message, $status), ErrorStatus::http($status)');
});

check('Handler e HttpErrorResponse usano la busta', function () use ($root) {
    return str_contains((string) file_get_contents($root.'/class/Api/Handler.php'), 'Envelope::error(')
        && str_contains((string) file_get_contents($root.'/class/Http/HttpErrorResponse.php'), 'Envelope::error(');
});

check('creazione rapida: busta in PHP e in JS', function () use ($root) {
    $http = (string) file_get_contents($root.'/app/http/backend/resource/quick-create.php');
    $js = (string) file_get_contents($root.'/class/Backend/Support/QuickCreateModal.php');

    return str_contains($http, 'Envelope::success(') && str_contains($http, 'Envelope::error(')
        && str_contains($js, 'res.response') && !str_contains($js, 'res.error');
});

check('SearchRemote apre la busta', fn () => str_contains((string) file_get_contents($root.'/class/Themes/Bootstrap/Form/Components/SearchRemote.php'), "data = data.response;"));

summary();
