<?php

use Wonder\Api\EndpointException;

$routeMeta = is_array($ROUTE_META ?? null) ? $ROUTE_META : [];
$routeParameters = is_array($ROUTE_PARAMETERS ?? null) ? $ROUTE_PARAMETERS : [];

$resourceSlug = trim((string) ($routeMeta['resource'] ?? ''));
$resourceAction = trim((string) ($routeMeta['resource_action'] ?? ''));

if ($resourceSlug === '' || $resourceAction === '') {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'status' => 500,
        'response' => 'Metadata resource api mancanti.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

try {
    $payload = \Wonder\Api\Support\ResourceApiController::fromSlug($resourceSlug)
        ->handle($resourceAction, $routeParameters);

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (EndpointException $exception) {
    http_response_code($exception->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'status' => $exception->getCode() ?: 400,
        'response' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    http_response_code((int) ($throwable->getCode() ?: 500));
    echo json_encode([
        'success' => false,
        'status' => (int) ($throwable->getCode() ?: 500),
        'response' => $throwable->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
