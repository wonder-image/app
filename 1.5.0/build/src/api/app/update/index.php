<?php

    require_once "../../config.php";

    use Wonder\Api\{ Endpoint, Handler, Response };

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
