<?php

use Wonder\App\Support\ApiRequest;

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$return = ['success' => 0];

if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    ApiRequest::json($return);
}

$image = $_FILES['image'];
$folder = empty($_SERVER['HTTP_DIR']) ? '' : '/'.trim((string) $_SERVER['HTTP_DIR'], '/').'/';
$tmpName = (string) ($image['name'] ?? '');
$fileSize = (int) ($image['size'] ?? 0);
$tmpFile = (string) ($image['tmp_name'] ?? '');
$mimeType = getMimeType($tmpName);
$extension = strtolower((string) pathinfo($tmpName, PATHINFO_EXTENSION));
$code = code(10, 'all');
$dir = rtrim((string) $PATH->rUpload, '/').$folder.$code;

if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$uploadImage = $dir.'/original'.($extension !== '' ? '.'.$extension : '');

if ($tmpFile !== '' && move_uploaded_file($tmpFile, $uploadImage)) {
    resizeImage($uploadImage, 600, 600, $dir, 'small');
    resizeImage($uploadImage, 1200, 1200, $dir, 'medium');
    resizeImage($uploadImage, 1920, 1920, $dir, 'large');

    $extensionSuffix = $extension !== '' ? '.'.$extension : '';

    $return = [
        'success' => 1,
        'file' => [
            'url' => $PATH->upload.$folder.$code.'/original'.$extensionSuffix,
            'original' => $PATH->upload.$folder.$code.'/original'.$extensionSuffix,
            'large' => $PATH->upload.$folder.$code.'/large'.$extensionSuffix,
            'medium' => $PATH->upload.$folder.$code.'/medium'.$extensionSuffix,
            'small' => $PATH->upload.$folder.$code.'/small'.$extensionSuffix,
            'size' => $fileSize,
            'name' => $tmpName,
            'extension' => $extension,
            'mime-type' => $mimeType,
        ],
    ];
}

ApiRequest::json($return);
