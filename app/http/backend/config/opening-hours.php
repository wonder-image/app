<?php

$routeMeta = is_array($ROUTE_META ?? null) ? $ROUTE_META : [];
$routeParameters = is_array($ROUTE_PARAMETERS ?? null) ? $ROUTE_PARAMETERS : [];

\Wonder\Backend\Support\OpeningHoursPageController::handle(
    (string) ($routeMeta['resource_action'] ?? 'edit'),
    (int) ($routeParameters['id'] ?? 0)
);
