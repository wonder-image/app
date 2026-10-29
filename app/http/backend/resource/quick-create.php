<?php

// Entry http della creazione rapida (sessione backend): delega al controller e
// risponde JSON nella busta comune. Nessuna logica qui. Il token @system resta lato controller.
use Wonder\Api\Envelope;
use Wonder\Backend\Support\QuickCreateController;

header('Content-Type: application/json; charset=utf-8');

$userAuthority = (array) ($USER->authority ?? []);
$result = \Wonder\Http\Csrf::verify()
    ? QuickCreateController::handle($_POST, $userAuthority)
    : ['success' => false, 'error' => \Wonder\Http\Csrf::failureMessage(), 'status' => 403];

$status = (int) ($result['status'] ?? 200);
$payload = !empty($result['success'])
    ? Envelope::success(['id' => $result['id'], 'label' => $result['label'], 'item' => $result['item']], $status)
    : Envelope::error((string) ($result['error'] ?? ''), $status);

http_response_code($status);

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
