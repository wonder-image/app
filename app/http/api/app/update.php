<?php

use Wonder\Api\{ Endpoint, Handler, Response };

/**
 * /api/app/update/
 *
 * Aggiorna lo schema dell'app (tabelle, build/row, build/update).
 *
 * Due percorsi di autenticazione:
 *
 * 1. **Deploy bootstrap (env-shared bearer)**.
 *    Se nel `.env` c'è `GITHUB_API_TOKEN` e il bearer della richiesta combacia
 *    esattamente, l'endpoint accetta la richiesta SENZA passare per il guard
 *    JWT/DB di `Wonder\Api\Endpoint`. Questo risolve il bootstrap circolare
 *    del primo deploy in produzione: prima della creazione di `api_users`
 *    nessun utente esiste, quindi nessun JWT è verificabile. Il token
 *    viene generato automaticamente da `php forge provision` e sincronizzato
 *    su Bitwarden Secrets Manager + GitHub Secrets della repository.
 *    Il confronto è time-safe (`hash_equals`).
 *
 * 2. **Flow JWT standard** (chiamate post-bootstrap, backend, altri client).
 *    Bearer = JWT firmato con APP_KEY. `Wonder\Api\Endpoint` verifica firma,
 *    decodifica `sub`, risolve `user_id` in `api_users` e controlla
 *    authority (`api_internal_user` o `api_public_access`).
 */

// 1) Deploy bootstrap
$envToken = trim((string) ($_ENV['GITHUB_API_TOKEN'] ?? ''));
if ($envToken !== '') {

    $authHeader = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if (!$authHeader && function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (preg_match('/Bearer\s(\S+)/', (string) $authHeader, $m) === 1
        && hash_equals($envToken, $m[1])
    ) {
        $payload = [];
        $body = file_get_contents('php://input');
        if (is_string($body) && $body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $source = trim((string) ($payload['source'] ?? 'github'));
        if (!in_array($source, [ 'github', 'backend' ], true)) {
            $source = 'github';
        }

        header('Content-Type: application/json; charset=utf-8');

        $RUNNER = new Wonder\App\UpdateRunner();
        $RESULT = $RUNNER->execute([
            'release_id' => trim((string) ($payload['release_id'] ?? '')),
            'trigger_type' => 'api',
            'source' => $source,
        ]);

        http_response_code($RESULT->success ? 200 : 500);
        echo $RUNNER->jsonPayload($RESULT);
        exit;
    }

}

// 2) Flow JWT standard
Handler::run('/api/app/update/', 'POST', [ 'api_internal_user', 'api_public_access' ], function (Endpoint $CALL) {

    $PARAMETERS = is_array($CALL->parameters) ? $CALL->parameters : [];
    $source = trim((string) ($PARAMETERS['source'] ?? 'github'));

    if (!in_array($source, [ 'github', 'backend' ], true)) {
        $source = 'github';
    }

    $RUNNER = new Wonder\App\UpdateRunner();
    $RESULT = $RUNNER->execute([
        'release_id' => trim((string) ($PARAMETERS['release_id'] ?? '')),
        'trigger_type' => 'api',
        'source' => $source,
    ]);

    return Response::raw(
        $RUNNER->jsonPayload($RESULT),
        $RESULT->success ? 200 : 500
    );

});
