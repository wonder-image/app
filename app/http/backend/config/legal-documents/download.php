<?php

$routeParameters = is_array($ROUTE_PARAMETERS ?? null) ? $ROUTE_PARAMETERS : [];
$id = (int) ($routeParameters['id'] ?? 0);

if ($id <= 0) {
    http_response_code(404);
    exit('Documento legale non trovato');
}

$DOCUMENT = infoLegalDocument($id);

if (!$DOCUMENT->exists) {
    http_response_code(404);
    exit('Documento legale non trovato');
}

$PDF = generateLegalDocumentPdf($DOCUMENT->id, [
    'output_mode' => 'S',
]);

if (!$PDF->success || empty($PDF->content)) {
    http_response_code(500);
    exit($PDF->message ?: 'Impossibile generare il PDF');
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$PDF->filename.'"');
header('Content-Length: '.strlen($PDF->content));

echo $PDF->content;
exit;
