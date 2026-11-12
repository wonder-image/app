<?php

use Wonder\App\Media\MediaLibrary;
use Wonder\App\Support\ApiRequest;

// Ricerca del selettore del campo media: ogni utente backend che modifica
// un record deve poter scegliere un media, quindi nessuna autorità oltre
// alla sessione. Escono solo id, nome, slug, testo alternativo, tipo e
// anteprima dei media non eliminati del tipo chiesto.

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

try {
    $result = MediaLibrary::search(
        ApiRequest::input('type'),
        ApiRequest::input('term', ''),
        ApiRequest::input('page', 1)
    );
} catch (Throwable $exception) {
    __log($exception, 'media', 'search');
    ApiRequest::error('La libreria Media non risponde.', 500);
}

if ($result === null) {
    ApiRequest::error('Parametri non validi.', 422);
}

ApiRequest::ok($result);
