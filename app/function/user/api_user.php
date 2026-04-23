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
        $providedToken = trim((string) ($POST['token'] ?? ''));

        if ($providedToken !== '') {

            $VALUES['token'] = $providedToken;

        } else if (!is_object($currentApiUser) || !($currentApiUser->exists ?? false) || empty($currentApiUser->token)) {

            $VALUES['token'] = Firebase\JWT\JWT::encode(
                [
                    'sub' => $USER_ID, # user_id
                    'iat' => time(),
                    'jti' => uniqid()
                ], 
                \Wonder\App\Credentials::appKey(), 
                'HS256'
            );

            $body = "
            Buongiorno <b>$USER->name</b>, <br>
            ecco il tuo Bearer token da includere nell'header di tutte le tue chiamate API:<br>
            <b>{$VALUES['token']}</b>";
            
            sendMail($SOCIETY->email, $USER->email, "Credenziali API", $body);

        } else {

            $VALUES['token'] = $currentApiUser->token;

        }

        $VALUES = formToArray('api_users', $VALUES, \Wonder\App\Table::key('api_users')->schema());
        
        if (
            (isset($USER->api_internal_user->exists) && $USER->api_internal_user->exists) 
            || (isset($USER->api_public_access->exists) && $USER->api_public_access->exists)
            ) {
            sqlModify('api_users', $VALUES, 'user_id', $USER_ID);
        } else {
            sqlInsert('api_users', $VALUES);
        }

        $RETURN->values = $VALUES;
        $RETURN->user = infoUser($USER_ID);

        return $RETURN;

    }

    function infoApiUser($value, $filter = 'user_id')
    {

        $RETURN = info('api_users', $filter, $value);

        return $RETURN;

    }
