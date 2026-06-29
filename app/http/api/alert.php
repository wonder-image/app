<?php

header('Content-Type: text/html; charset=utf-8');

$alert = trim((string) ($_POST['alert'] ?? ''));

if ($alert === '') {
    echo '';
    return;
}

echo alertTheme(
    $alert,
    $_POST['alertType'] ?? null,
    $_POST['alertTitle'] ?? null,
    $_POST['alertText'] ?? null,
);
