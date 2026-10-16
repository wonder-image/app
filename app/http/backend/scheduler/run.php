<?php

use Wonder\App\Resources\Scheduler\ScheduleResource;
use Wonder\App\Scheduler\Repository;
use Wonder\Http\Csrf;

$routeParameters = is_array($ROUTE_PARAMETERS ?? null) ? $ROUTE_PARAMETERS : [];
$id = (int) ($routeParameters['id'] ?? 0);
$respond = static function (int $status, array $body): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

if (!Csrf::verify()) {
    $respond(403, ['message' => 'Sessione scaduta: ricarica la pagina e riprova.']);
}

try {
    $after = (new Repository())->request($id);
} catch (\InvalidArgumentException $exception) {
    $respond(422, ['message' => $exception->getMessage()]);
}

$respond(202, [
    'after' => $after,
    'status_url' => ScheduleResource::url(['id' => $id], 'run-status').'?after='.$after,
    'message' => 'Esecuzione richiesta: partirà al prossimo passaggio dello scheduler.',
]);
