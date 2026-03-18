<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = ['admin'];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    require_once "set-up.php";

    $DOCUMENT = infoLegalDocument($_GET['id'] ?? 0);

    if (!$DOCUMENT->exists) {
        http_response_code(404);
        exit('Documento legale non trovato');
    }

    // Genero il PDF al volo e lo scarico subito.
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
