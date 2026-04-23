<?php

$resourceClass = \Wonder\App\Resources\Config\ConfigurationFileResource::class;
$title = 'Modifica i file di configurazione';
$htaccessPath = rtrim((string) $ROOT, '/').'/.htaccess';
$robotsPath = rtrim((string) $ROOT, '/').'/robots.txt';
$errors = [];

if (isset($_POST['modify'])) {
    $htaccess = (string) ($_POST['htaccess'] ?? '');
    $robots = (string) ($_POST['robots'] ?? '');

    $htaccessWritten = @file_put_contents($htaccessPath, $htaccess);
    $robotsWritten = @file_put_contents($robotsPath, $robots);

    if ($htaccessWritten === false) {
        $errors['htaccess'] = 'Impossibile salvare il file .htaccess.';
    }

    if ($robotsWritten === false) {
        $errors['robots'] = 'Impossibile salvare il file robots.txt.';
    }

    if ($errors === []) {
        header('Location: '.__r('backend.config.configuration-file').'?alert=654');
        exit();
    }
}

\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/config/configuration-file.php', [
    'TITLE' => $title,
    'BACK_URL' => '',
    'RESOURCE_CLASS' => $resourceClass,
    'HTACCESS_PATH' => $htaccessPath,
    'ROBOTS_PATH' => $robotsPath,
    'HTACCESS_VALUE' => (string) ($_POST['htaccess'] ?? (@file_get_contents($htaccessPath) ?: '')),
    'ROBOTS_VALUE' => (string) ($_POST['robots'] ?? (@file_get_contents($robotsPath) ?: '')),
    'ERRORS' => $errors,
])->render();
