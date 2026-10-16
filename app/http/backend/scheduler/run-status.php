<?php

use Wonder\App\Scheduler\Repository;

$routeParameters = is_array($ROUTE_PARAMETERS ?? null) ? $ROUTE_PARAMETERS : [];
$id = (int) ($routeParameters['id'] ?? 0);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $status = (new Repository())->runStatus($id, max(0, (int) ($_GET['after'] ?? 0)));
} catch (\InvalidArgumentException $exception) {
    http_response_code(404);
    echo json_encode(['message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$heartbeat = $status['heartbeat'] ? strtotime($status['heartbeat'].' UTC') : false;
$status['heartbeat_age'] = $heartbeat === false ? null : max(0, time() - $heartbeat);
if ($status['run'] !== null) {
    $status['run']['url'] = '/backend/app/scheduler/runs/'.(int) $status['run']['id'].'/';
}

echo json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
