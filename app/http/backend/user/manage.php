<?php

$routeMeta = is_array($ROUTE_META ?? null) ? $ROUTE_META : [];
$routeParameters = is_array($ROUTE_PARAMETERS ?? null) ? $ROUTE_PARAMETERS : [];

$resourceSlug = trim((string) ($routeMeta['resource'] ?? ''));
$resourceAction = trim((string) ($routeMeta['resource_action'] ?? ''));

if ($resourceSlug === '' || $resourceAction === '') {
    throw new RuntimeException('Metadata user backend mancanti.');
}

\Wonder\Backend\Support\UserManagementPageController::fromSlug($resourceSlug)
    ->handle($resourceAction, $routeParameters);
