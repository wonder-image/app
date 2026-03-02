<?php

namespace Wonder\Http\Symfony\Controllers;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class GatewayController
{
    public function __invoke(Request $request): JsonResponse
    {
        $gatewayPath = rtrim((string) $request->getScriptName(), '/');

        return new JsonResponse([
            'success' => true,
            'message' => 'Symfony 8 gateway attivo',
            'usage' => [
                'health' => $gatewayPath.'?r=/health',
                'root' => $gatewayPath,
            ],
        ]);
    }
}
