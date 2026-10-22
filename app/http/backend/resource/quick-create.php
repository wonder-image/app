<?php

// Entry http della creazione rapida (sessione backend): delega al controller e
// risponde JSON. Nessuna logica qui. Il token @system resta lato controller.
use Wonder\Backend\Support\QuickCreateController;

header('Content-Type: application/json; charset=utf-8');

$userAuthority = (array) ($USER->authority ?? []);
$result = \Wonder\Http\Csrf::verify()
    ? QuickCreateController::handle($_POST, $userAuthority)
    : ['success' => false, 'error' => 'La pagina è scaduta o la richiesta non è valida. Ricarica la pagina e riprova.', 'status' => 403];

http_response_code((int) ($result['status'] ?? 200));
unset($result['status']);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
