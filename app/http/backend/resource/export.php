<?php

$routeMeta = is_array($ROUTE_META ?? null) ? $ROUTE_META : [];
$routeParameters = is_array($ROUTE_PARAMETERS ?? null) ? $ROUTE_PARAMETERS : [];

$resourceSlug = trim((string) ($routeMeta['resource'] ?? ''));
$format = trim((string) ($routeParameters['format'] ?? ''));

if ($resourceSlug === '' || $format === '') {
    throw new RuntimeException('Metadata export resource backend mancanti.');
}

\Wonder\Backend\Support\ResourceDownloadController::fromSlug($resourceSlug)
    ->handle($format);
