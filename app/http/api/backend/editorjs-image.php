<?php

use Wonder\App\Support\ApiRequest;
use Wonder\App\Support\UploadDir;
use Wonder\App\Support\UploadValidator;

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$fail = static function (int $status, int $alert): never {
    ApiRequest::json(['success' => 0, 'alert' => $alert], $status);
};

$image = $_FILES['image'] ?? null;
$folder = UploadDir::normalize((string) ($_SERVER['HTTP_DIR'] ?? ''));

if ($folder === null) {
    $fail(400, UploadValidator::UPLOAD_FAILED);
}

$alert = UploadValidator::validate($image, UploadValidator::IMAGE_TYPES);

if ($alert !== null) {
    $fail($alert === UploadValidator::SIZE ? 413 : 422, $alert);
}

$tmpFile = (string) $image['tmp_name'];

if (!is_uploaded_file($tmpFile)) {
    $fail(400, UploadValidator::UPLOAD_FAILED);
}

// Il contenuto deve essere un'immagine decodificabile prima di spostarlo.
if (@getimagesize($tmpFile) === false) {
    $fail(422, UploadValidator::MIME_MISMATCH);
}

$tmpName = (string) $image['name'];
$extension = UploadValidator::extension($tmpName);
$code = UploadDir::randomName();
$dir = UploadDir::ensure((string) $PATH->rUpload, $folder.$code.'/');

if ($dir === null) {
    $fail(500, UploadValidator::UPLOAD_FAILED);
}

$removeUpload = static function () use ($dir): void {
    foreach (glob($dir.'*') ?: [] as $created) {
        if (is_file($created)) {
            unlink($created);
        }
    }

    @rmdir($dir);
};

$uploadImage = $dir.'original.'.$extension;

if (!move_uploaded_file($tmpFile, $uploadImage)) {
    $removeUpload();
    $fail(500, UploadValidator::UPLOAD_FAILED);
}

$resized = resizeImage($uploadImage, 600, 600, rtrim($dir, '/'), 'small')
    && resizeImage($uploadImage, 1200, 1200, rtrim($dir, '/'), 'medium')
    && resizeImage($uploadImage, 1920, 1920, rtrim($dir, '/'), 'large');

if (!$resized) {
    $removeUpload();
    $fail(422, 926);
}

$url = rtrim((string) $PATH->upload, '/').$folder.$code.'/';

ApiRequest::json([
    'success' => 1,
    'file' => [
        'url' => $url.'original.'.$extension,
        'original' => $url.'original.'.$extension,
        'large' => $url.'large.'.$extension,
        'medium' => $url.'medium.'.$extension,
        'small' => $url.'small.'.$extension,
        'size' => (int) filesize($uploadImage),
        'name' => $tmpName,
        'extension' => $extension,
        'mime-type' => getMimeType($tmpName),
    ],
]);
