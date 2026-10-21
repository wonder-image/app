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

$file = $_FILES['file'] ?? null;
$folder = UploadDir::normalize((string) ($_SERVER['HTTP_DIR'] ?? ''));

if ($folder === null) {
    $fail(400, UploadValidator::UPLOAD_FAILED);
}

$alert = UploadValidator::validate($file, UploadValidator::FILE_TYPES);

if ($alert !== null) {
    $fail($alert === UploadValidator::SIZE ? 413 : 422, $alert);
}

$tmpFile = (string) $file['tmp_name'];

if (!is_uploaded_file($tmpFile)) {
    $fail(400, UploadValidator::UPLOAD_FAILED);
}

$dir = UploadDir::ensure((string) $PATH->rUpload, $folder);

if ($dir === null) {
    $fail(500, UploadValidator::UPLOAD_FAILED);
}

$tmpName = (string) $file['name'];
$extension = UploadValidator::extension($tmpName);
$fileName = UploadDir::randomName().'.'.$extension;

if (!move_uploaded_file($tmpFile, $dir.$fileName)) {
    $fail(500, UploadValidator::UPLOAD_FAILED);
}

ApiRequest::json([
    'success' => 1,
    'file' => [
        'url' => rtrim((string) $PATH->upload, '/').$folder.$fileName,
        'size' => (int) filesize($dir.$fileName),
        'name' => $tmpName,
        'extension' => $extension,
        'mime-type' => getMimeType($tmpName),
    ],
]);
