<?php

namespace Wonder\Http\Symfony\Controllers;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class HealthController
{
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'status' => 'ok',
            'service' => 'wonder-image/sf8-gateway',
            'php' => PHP_VERSION,
            'time' => date('c'),
        ]);
    }
}
