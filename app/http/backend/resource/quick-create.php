<?php

// Entry http della creazione rapida (sessione backend): delega al controller e
// risponde JSON. Nessuna logica qui. Il token @system resta lato controller.
use Wonder\Backend\Support\QuickCreateController;

header('Content-Type: application/json; charset=utf-8');

$userAuthority = (array) ($USER->authority ?? []);
$result = QuickCreateController::handle($_POST, $userAuthority);

http_response_code((int) ($result['status'] ?? 200));
unset($result['status']);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
