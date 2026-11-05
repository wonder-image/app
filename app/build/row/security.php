<?php

    // `api_key` identifica il sito presso api.wonderimage.it: va salvata una volta,
    // altrimenti `Credentials::api()` ne genera una nuova a ogni processo.
    // Con `API_KEY` nel .env vince quella e il database non la duplica.
    $securityApiKey = trim((string) ($_ENV['API_KEY'] ?? '')) === ''
        ? trim((string) \Wonder\App\Credentials::api()->key)
        : '';

    $securityRow = sqlSelect('security', ['id' => 1], 1);

    if (!$securityRow->exists) {

        $values = \Wonder\App\SeedDefaults::securityRow($securityApiKey);
        $values['id'] = 1;

        sqlInsert('security', $values);

    } elseif ($securityApiKey !== '' && trim((string) ($securityRow->row['api_key'] ?? '')) === '') {

        sqlModify('security', ['api_key' => $securityApiKey], 'id', 1);

    }

    unset($securityApiKey, $securityRow);
