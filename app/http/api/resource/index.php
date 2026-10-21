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
    http_response_code(\Wonder\Api\ErrorStatus::http($exception->getCode() ?: 400));
    echo json_encode([
        'success' => false,
        'status' => $exception->getCode() ?: 400,
        'response' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $throwable) {
    $errorCode = (int) ($throwable->getCode() ?: 500);
    [$errorMessage, $errorRequestId] = \Wonder\Api\Handler::throwableResponse($errorCode, $throwable);

    if ($errorRequestId !== null) {
        \Wonder\App\Logger::log($throwable, 'api', 'resource_api', 'ERROR', 'error/api', [
            'resource' => $resourceSlug,
            'action' => $resourceAction,
        ], false);
    }

    $errorPayload = [
        'success' => false,
        'status' => $errorCode,
        'response' => $errorMessage,
    ];
    if ($errorRequestId !== null) {
        $errorPayload['request_id'] = $errorRequestId;
    }

    http_response_code(\Wonder\Api\ErrorStatus::http($errorCode));
    echo json_encode($errorPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
