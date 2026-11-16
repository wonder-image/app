<?php

use Wonder\App\Media\MediaDownloads;
use Wonder\App\Media\MediaFile;

// Scarica in un unico zip l'originale di un media e i file generati dal
// ridimensionatore. Solo lettura: niente CSRF, niente scritture.
$routeParameters = is_array($ROUTE_PARAMETERS ?? null) ? $ROUTE_PARAMETERS : [];
$media = MediaFile::find($routeParameters['id'] ?? null);
$files = $media === null ? [] : MediaDownloads::files(MediaDownloads::of($media));

if ($files === [] || !class_exists('ZipArchive')) {
    http_response_code(404);
    echo 'File non trovato.';
    exit;
}

$path = tempnam(sys_get_temp_dir(), 'media-zip-');
$zip = new ZipArchive();

if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    echo 'Non è stato possibile preparare lo zip.';
    exit;
}

foreach ($files as $file) {
    $zip->addFile($file['path'], $file['name']);
}

$zip->close();

$name = ($media->slug !== '' ? $media->slug : pathinfo($media->file, PATHINFO_FILENAME)).'.zip';
$name = preg_replace('/[^A-Za-z0-9._-]/', '-', $name);

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$name.'"');
header('Content-Length: '.(string) filesize($path));
header('Cache-Control: no-store');

readfile($path);
unlink($path);
exit;
