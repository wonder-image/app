<?php

    $adminUsername = trim((string) ($_ENV['USER_USERNAME'] ?? \Wonder\App\RuntimeDefaults::adminUsername()));
    $adminName = trim((string) ($_ENV['USER_NAME'] ?? \Wonder\App\RuntimeDefaults::adminName()));
    $adminSurname = trim((string) ($_ENV['USER_SURNAME'] ?? \Wonder\App\RuntimeDefaults::adminSurname()));
    $adminEmail = trim((string) ($_ENV['USER_EMAIL'] ?? \Wonder\App\RuntimeDefaults::adminEmail()));

    if (!sqlSelect('user', [ 'username' => $adminUsername ], 1)->exists) {

        $values = [
            "name" => $adminName,
            "surname" => $adminSurname,
            "email" => $adminEmail,
            "username" => $adminUsername,
            "password" => $_ENV['USER_PASSWORD'],
            "authority" => "admin",
            "area" => "backend",
            "active" => "true"
        ];

        user($values);

    }

    $systemAllowedDomains = array_values(array_filter(array_unique([
        trim((string) ($PAGE->domain ?? '')),
        trim((string) parse_url((string) ($_ENV['APP_URL'] ?? ''), PHP_URL_HOST)),
    ])));

    if (!sqlSelect('user', [ 'username' => '@system' ], 1)->exists) {
        
        $values = [
            "name" => "API",
            "surname" => "System",
            "email" => \Wonder\App\RuntimeDefaults::systemEmail(),
            "username" => "@system",
            "password" => $_ENV['USER_PASSWORD'],
            "authority" => "api_internal_user",
            "area" => "api",
            "active" => "true",
            "allowed_domains" => $systemAllowedDomains
        ];

        user($values);

    }

    $systemUser = infoUser('@system', 'username');

    if ($systemUser->exists ?? false) {

        $systemAuthority = is_array($systemUser->authority ?? null) ? $systemUser->authority : [];
        $systemArea = is_array($systemUser->area ?? null) ? $systemUser->area : [];
        $mustUpdateSystemUser = false;

        if (!in_array('api_internal_user', $systemAuthority, true)) {
            $systemAuthority[] = 'api_internal_user';
            $mustUpdateSystemUser = true;
        }

        if (!in_array('api', $systemArea, true)) {
            $systemArea[] = 'api';
            $mustUpdateSystemUser = true;
        }

        if (($systemUser->active ?? false) !== true) {
            $mustUpdateSystemUser = true;
        }

        if ($mustUpdateSystemUser) {
            sqlModify('user', [
                'authority' => json_encode(array_values(array_unique($systemAuthority))),
                'area' => json_encode(array_values(array_unique($systemArea))),
                'active' => 'true',
            ], 'id', $systemUser->id);

            $systemUser = infoUser($systemUser->id);
        }

        $systemApiUser = infoApiUser($systemUser->id);

        if (!($systemApiUser->exists ?? false) || empty($systemApiUser->token ?? '')) {
            apiUser([
                'authority' => 'api_internal_user',
                'area' => 'api',
                'active' => 'true',
                'allowed_domains' => $systemAllowedDomains,
                '_skip_api_token_mail' => true,
            ], [], $systemUser, $systemUser->id);
        }

    }
