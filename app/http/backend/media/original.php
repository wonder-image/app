<?php

use Wonder\App\Media\MediaDownloads;
use Wonder\App\Media\MediaFile;

// Scarica il file originale di un media, così come è stato caricato.
// Solo lettura: niente CSRF, niente scritture.
$routeParameters = is_array($ROUTE_PARAMETERS ?? null) ? $ROUTE_PARAMETERS : [];
$media = MediaFile::find($routeParameters['id'] ?? null);
$original = $media === null ? null : (MediaDownloads::of($media)['groups'][0]['files'][0] ?? null);

if (!is_array($original) || !is_file($original['path'])) {
    http_response_code(404);
    echo 'File non trovato.';
    exit;
}

$name = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $original['name']);
$type = function_exists('mime_content_type') ? (mime_content_type($original['path']) ?: '') : '';

header('Content-Type: '.($type !== '' ? $type : 'application/octet-stream'));
header('Content-Disposition: attachment; filename="'.$name.'"');
header('Content-Length: '.(string) filesize($original['path']));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

readfile($original['path']);
exit;
