<?php

// L'anteprima di un esempio del catalogo dentro il backend: un documento a sé
// (va in un iframe), con i soli asset del tema chiesto e, per il tema Wonder,
// i token CSS del sito. Parametri: component, example, theme, scheme.

use Wonder\App\Credentials;
use Wonder\Docs\PreviewPage;
use Wonder\View\View;

try {
    $PREVIEW = PreviewPage::build($_GET, (string) $ROOT_APP, false);
} catch (InvalidArgumentException $exception) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $exception->getMessage();

    return;
}

$PREVIEW['paths'] = [
    'site' => (string) ($PATH->site ?? ''),
    'app' => (string) ($PATH->app ?? ''),
    'api' => (string) ($PATH->api ?? ''),
];
$PREVIEW['token'] = (string) Credentials::appToken();
$PREVIEW['lang'] = function_exists('__l') ? (string) __l() : 'it';

View::make($ROOT_APP.'/view/pages/docs/preview.php', $PREVIEW)->render();
