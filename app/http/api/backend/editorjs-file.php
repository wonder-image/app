<?php

use Wonder\App\Support\ApiRequest;

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$return = ['success' => 0];

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    ApiRequest::json($return);
}

$file = $_FILES['file'];
$folder = empty($_SERVER['HTTP_DIR']) ? '' : '/'.trim((string) $_SERVER['HTTP_DIR'], '/').'/';
$tmpFile = $file['tmp_name'] ?? '';
$tmpName = $file['name'] ?? '';
$fileSize = (int) ($file['size'] ?? 0);
$mimeType = getMimeType((string) $tmpName);
$extension = strtolower((string) pathinfo((string) $tmpName, PATHINFO_EXTENSION));
$code = code(10, 'all');
$dir = rtrim((string) $PATH->rUpload, '/').$folder;
$fileName = $extension !== '' ? $code.'.'.$extension : $code;
$uploadFile = $dir.$fileName;

if ($tmpFile !== '' && move_uploaded_file((string) $tmpFile, $uploadFile)) {
    $return = [
        'success' => 1,
        'file' => [
            'url' => $PATH->upload.$folder.$fileName,
            'size' => $fileSize,
            'name' => $tmpName,
            'extension' => $extension,
            'mime-type' => $mimeType,
        ],
    ];
}

ApiRequest::json($return);
