<?php

use Wonder\App\Import\ImportError;
use Wonder\App\Import\Sample;
use Wonder\App\Resources\Config\ImportResource;

// Il file di esempio di un tracciato (?layout=&format=xlsx|csv).
$format = (string) ($_GET['format'] ?? 'xlsx');

if (!in_array($format, Sample::FORMATS, true)) {
    http_response_code(404);
    exit;
}

try {
    $layout = ImportResource::layout((string) ($_GET['layout'] ?? ''));
} catch (ImportError $exception) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo $exception->getMessage();
    exit;
}

$body = Sample::render($layout, $format);

header('Content-Type: '.Sample::contentType($format));
header('Content-Disposition: attachment; filename="'.Sample::filename($layout, $format).'"');
header('Content-Length: '.strlen($body));
header('Cache-Control: no-store');
echo $body;
exit;
