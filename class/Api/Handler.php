<?php

namespace Wonder\Api;

use Throwable;
use Wonder\App\Debug;
use Wonder\App\Logger;
use Wonder\Http\HttpErrorResponse;
use Wonder\Http\RequestId;

class Handler
{
    /** @param bool $allowPageToken false per gli endpoint solo server: rifiutano il token di pagina */
    public static function run(string $endpoint, string $method, array|string|null $auth, callable $callback, bool $allowPageToken = true): never
    {
        try {
            $call = new Endpoint($endpoint, $method, $auth, $allowPageToken);
            $response = $callback($call);

            self::send($response);
        } catch (EndpointException $exception) {
            self::sendError($exception->getCode() ?: 400, $exception->getMessage());
        } catch (Throwable $throwable) {
            Logger::log(
                $throwable,
                'api',
                'legacy_handler',
                'ERROR',
                'error/api',
                [
                    'endpoint' => $endpoint,
                    'method' => $method,
                ],
                false
            );
            $code = (int) ($throwable->getCode() ?: 500);
            [$message, $requestId] = self::throwableResponse($code, $throwable);
            self::sendError($code, $message, $requestId);
        }
    }

    private static function send(mixed $response): never
    {
        if ($response instanceof Response) {
            http_response_code($response->status);

            if ($response->raw) {
                echo $response->payload;
                exit();
            }

            echo json_encode($response->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit();
        }

        http_response_code(200);

        if (is_string($response)) {
            echo $response;
            exit();
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    /**
     * Testo e request id per un'eccezione non prevista: sui 500 il messaggio
     * solo con debug, altrimenti quello generico + `request_id`. I 4xx restano
     * com'erano.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function throwableResponse(int $code, Throwable $throwable): array
    {
        if (ErrorStatus::http($code) < 500) {
            return [$throwable->getMessage(), null];
        }

        $message = Debug::enabled() ? trim($throwable->getMessage()) : '';

        return [$message !== '' ? $message : HttpErrorResponse::GENERIC_MESSAGE, RequestId::current()];
    }

    private static function sendError(int $status, string $message, ?string $requestId = null): never
    {
        http_response_code(ErrorStatus::http($status));
        if ($requestId !== null && !headers_sent()) {
            header('X-Request-Id: '.$requestId);
        }

        $payload = [
            'success' => false,
            'status' => $status,
            'response' => $message,
        ];
        if ($requestId !== null) {
            $payload['request_id'] = $requestId;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit();
    }
}
