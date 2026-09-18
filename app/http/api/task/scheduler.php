<?php

use Wonder\Api\{Endpoint, Handler, Response};
use Wonder\App\Scheduler\Repository;

Handler::run('/api/task/scheduler/', 'POST', 'api_internal_user', function (Endpoint $call) {
    $call->requireUsername('@system')->checkParameters(['schedule_id']);
    $id = filter_var($call->parameters['schedule_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) { throw new \Wonder\Api\EndpointException('schedule_id non valido.', 422); }
    (new Repository())->request($id);
    return Response::json(['success' => true, 'queued' => true], 202);
});
