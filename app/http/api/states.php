<?php

use Wonder\App\Support\ApiRequest;

header('Content-Type: application/json; charset=utf-8');

$country = trim((string) ($_POST['country'] ?? ''));

if ($country === '') {
    echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

echo json_encode(states($country), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
