<?php

$routeMeta = is_array($ROUTE_META ?? null) ? $ROUTE_META : [];
$routeParameters = is_array($ROUTE_PARAMETERS ?? null) ? $ROUTE_PARAMETERS : [];

$resourceSlug = trim((string) ($routeMeta['resource'] ?? ''));
$format = \Wonder\Backend\Support\ResourceDownloadController::formatFrom($routeParameters, $_GET);

if ($resourceSlug === '' || $format === '') {
    throw new RuntimeException('Metadata export resource backend mancanti.');
}

// `?layout=ID`: il tracciato salvato da usare (solo se è di questa Resource).
$layoutId = filter_var($_GET['layout'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

\Wonder\Backend\Support\ResourceDownloadController::fromSlug($resourceSlug)
    ->handle($format, $layoutId === false ? null : $layoutId);
