<?php

    function apiUser($POST, $UPLOAD, $USER, $MODIFY_ID = null)
    {

        global $SOCIETY;

        $RETURN = (object) [];

        $USER_ID = $MODIFY_ID ?? $USER->id;

        $VALUES = $POST;
        $VALUES['user_id'] = $USER_ID;
        $authority = trim((string) ($POST['authority'] ?? ''));
        $currentApiUser = ($authority !== '' && isset($USER->$authority) && is_object($USER->$authority)) ? $USER->$authority : null;
        $existingApiUser = infoApiUser($USER_ID);
        if ((!is_object($currentApiUser) || !($currentApiUser->exists ?? false)) && ($existingApiUser->exists ?? false)) {
            $currentApiUser = $existingApiUser;
        }
        $providedToken = trim((string) ($POST['token'] ?? ''));
        $skipTokenMail = !empty($POST['_skip_api_token_mail']);
        // Durata del token nuovo in secondi: assente = API_TOKEN_TTL, 0 = senza scadenza.
        $tokenTtl = isset($POST['_api_token_ttl']) && is_numeric($POST['_api_token_ttl']) ? max(0, (int) $POST['_api_token_ttl']) : null;
        unset($VALUES['_api_token_ttl']);

        // 1. Risolvi il token: provided > nuovo JWT > riuso esistente.
        $tokenIsNew = false;

        if ($providedToken !== '') {

            $VALUES['token'] = $providedToken;

        } else if (!is_object($currentApiUser) || !($currentApiUser->exists ?? false) || empty($currentApiUser->token)) {

            $VALUES['token'] = \Wonder\Api\Token\ApiTokens::issue($USER_ID, $tokenTtl);

            $tokenIsNew = true;

        } else {

            $VALUES['token'] = $currentApiUser->token;

        }

        // 2. Persisti la riga in `api_users` PRIMA di tentare l'invio mail.
        //    Storicamente l'invio era prima dell'INSERT: se SMTP era down o
        //    le credenziali mail non erano ancora configurate (es. al primo
        //    deploy in produzione su shared hosting che non ha SMTP locale)
        //    `sendMail()` lanciava exception e bloccava l'INSERT. Risultato:
        //    api_users restava vuoto e `Credentials::appToken()` restituiva
        //    stringa vuota, facendo fallire ogni chiamata API frontend con
        //    "Bearer mancante". Adesso l'INSERT è sempre eseguito, la mail
        //    è un side-effect non bloccante.
        $isUpdate = is_object($currentApiUser) && ($currentApiUser->exists ?? false);

        // In modifica la riga corrente fa da OLD_VALUES: il controllo di
        // unicità del token esclude la riga stessa, come negli altri form.
        $VALUES = formToArray('api_users', $VALUES, \Wonder\App\Table::key('api_users')->schema(), $isUpdate ? (array) $currentApiUser : null);

        if ($isUpdate) {
            // formToArray() aggiunge l'id della riga: l'UPDATE resta su
            // user_id e il payload restituito non cambia.
            unset($VALUES['id']);
            sqlModify('api_users', $VALUES, 'user_id', $USER_ID);
        } else {
            sqlInsert('api_users', $VALUES);
        }

        // 3. Notifica via mail il proprietario del token, ma solo:
        //    - se il token è stato generato ora (non al riuso);
        //    - se il chiamante non ha richiesto skip esplicito;
        //    - se abbiamo i dati minimi (mittente/destinatario);
        //    - in try/catch: una mail fallita non deve invalidare un INSERT
        //      che è già andato a buon fine.
        if (
            $tokenIsNew
            && !$skipTokenMail
            && is_object($SOCIETY)
            && !empty($SOCIETY->email)
            && !empty($USER->email)
        ) {
            try {

                $body = "
                Buongiorno <b>$USER->name</b>, <br>
                ecco il tuo Bearer token da includere nell'header di tutte le tue chiamate API:<br>
                <b>{$VALUES['token']}</b>";

                sendMail($SOCIETY->email, $USER->email, "Credenziali API", $body);

            } catch (Throwable $e) {
                error_log('[apiUser] sendMail fallito ma il token è già stato salvato: '.$e->getMessage());
            }
        }

        $RETURN->values = $VALUES;
        $RETURN->user = infoUser($USER_ID);

        return $RETURN;

    }

    /**
     * Rigenera il token API di un utente: il vecchio smette subito di valere
     * (`Endpoint` confronta il bearer con `api_users.token`). Restituisce il
     * nuovo token, o stringa vuota se l'utente non ha una riga `api_users`.
     * Per `@system` rigenera anche i token di pagina (`tkh` cambia).
     *
     * @param int|null $ttl secondi; `null` = API_TOKEN_TTL, `0` = senza scadenza
     */
    function regenerateApiToken($USER_ID, ?int $ttl = null): string
    {

        $current = infoApiUser($USER_ID);

        if (!($current->exists ?? false)) {
            return '';
        }

        $token = \Wonder\Api\Token\ApiTokens::issue($USER_ID, $ttl);

        sqlModify('api_users', [ 'token' => $token ], 'user_id', $USER_ID);

        return $token;

    }

    function infoApiUser($value, $filter = 'user_id')
    {

        $RETURN = info('api_users', $filter, $value);

        return $RETURN;

    }
