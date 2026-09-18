<?php

use Wonder\Api\{Endpoint, Handler, Response};
use Wonder\App\Scheduler\Repository;

Handler::run('/api/task/sitemap/', 'POST', 'api_internal_user', function (Endpoint $call) {
    $call->requireUsername('@system');
    $repository = new Repository();
    $rows = $repository->rows("SELECT id FROM scheduler_schedules WHERE task_key = 'wonder.sitemap' AND enabled = 'true' AND deleted = 'false' ORDER BY id LIMIT 1");
    if (!$rows) { throw new \Wonder\Api\EndpointException('Sitemap non configurata o sospesa.', 409); }
    $repository->request((int) $rows[0]['id']);
    return Response::json(['success' => true, 'queued' => true], 202);
});
