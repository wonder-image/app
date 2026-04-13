<?php

namespace Wonder\Api;

use Throwable;

class Handler
{
    public static function run(string $endpoint, string $method, array|string|null $auth, callable $callback): never
    {
        try {
            $call = new Endpoint($endpoint, $method, $auth);
            $response = $callback($call);

            self::send($response);
        } catch (EndpointException $exception) {
            self::sendError($exception->getCode() ?: 400, $exception->getMessage());
        } catch (Throwable $throwable) {
            self::sendError((int) ($throwable->getCode() ?: 500), $throwable->getMessage());
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

    private static function sendError(int $status, string $message): never
    {
        http_response_code($status);

        echo json_encode([
            'success' => false,
            'status' => $status,
            'response' => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit();
    }
}
