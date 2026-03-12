<?php

    // Configurazione verifica email richiesta per il login in una specifica area.
    function userLoginEmailVerificationConfig(object $U, string $AREA, $PERMIT_REQUIRED = null): array
    {

        $authorities = isset($U->authority) && is_array($U->authority) ? $U->authority : [];
        $candidates = [];

        if ($PERMIT_REQUIRED !== null) {
            $requiredList = is_array($PERMIT_REQUIRED) ? $PERMIT_REQUIRED : [ $PERMIT_REQUIRED ];
            foreach ($requiredList as $requiredAuthority) {
                if (is_string($requiredAuthority) && in_array($requiredAuthority, $authorities, true)) {
                    $candidates[] = $requiredAuthority;
                }
            }
        }

        if (count($candidates) === 0) {
            foreach ($authorities as $authority) {
                $permission = permissions($authority);
                if (is_object($permission) && isset($permission->area) && $permission->area === $AREA) {
                    $candidates[] = $authority;
                }
            }
        }

        foreach ($candidates as $authority) {

            $permission = permissions($authority);
            $rules = userPermissionVerificationRules($permission);
            $config = userPermissionEmailVerificationConfig($permission, $rules, 'login');

            if (($config['required'] ?? false) === true) {
                $config['authority'] = $authority;
                return $config;
            }

        }

        return [
            'required' => false,
            'authority' => '',
            'token_link' => '',
            'sent_link' => '',
            'flow' => 'login',
            'redirect_base64' => '',
            'requested_from_url' => '',
            'ttl_hours' => 24,
        ];

    }

    // Autorizzazione pagina privata (backend/frontend)
    function authorizeUser($AREA, $PERMIT_REQUIRED, $USER_ID = null) {

        global $PAGE;
        global $PERMITS;
        
        $alert = "";

        if (count($PERMIT_REQUIRED) >= 1) {
            $login = isset($PERMITS[$AREA][$PERMIT_REQUIRED[0]]['links']['login']) ? $PERMITS[$AREA][$PERMIT_REQUIRED[0]]['links']['login'] : $PERMITS[$AREA]['links']['login'];
        } else {
            $login = $PERMITS[$AREA]['links']['login'];
        }

        $login_redirect = "$login?redirect=$PAGE->uriBase64";

        if ($USER_ID == null) {
            $rememberedId = Wonder\Auth\RememberMe::tryLogin($AREA);
            if (!empty($rememberedId)) { $USER_ID = $rememberedId; }
        }

        if ($USER_ID == null) {

            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                $separator = (strpos($login_redirect, '?') !== false) ? '&' : '?';
                $login_redirect .= $separator."alert=917";
                Wonder\Auth\AuthLog::write('session_expired', null, $AREA, false, [ 'uri' => $_SERVER['REQUEST_URI'] ?? '' ]);
            }
                
            header("Location: $login_redirect");
            exit;

        } else {

            $U = infoUser($USER_ID);

            if ($U->exists) {
                if (!$U->deleted) {
                    if ($U->active){
                        if (in_array($AREA, $U->area)) {
                            if (count($PERMIT_REQUIRED) == 0 || count(array_intersect($PERMIT_REQUIRED, $U->authority)) >= 1) {
                                return $U;
                            } else {
                                $alert = 915;
                            }
                        } else {
                            $alert = 911;
                        }
                    } else {
                        $alert = 909;
                    }
                } else {
                    $alert = 912;
                }
            } else {
                $alert = 901;
            }

            if (!empty($alert)) {
                header("Location: $login?alert=$alert");
            }

        }

    }

    // Login utente (remember-me automatico)
    function authenticateUser($KEY, $VALUE, $PASSWORD, $AREA, $PERMIT_REQUIRED = null)
    {

        global $ALERT;

        $VALUE = sanitize($VALUE);
        $PASSWORD = sanitize($PASSWORD);

        $U = infoUser($VALUE, $KEY);
        $reason = '';

        if (!$U->exists) {
            $ALERT = ($KEY === 'email') ? 904 : 901;
            $reason = 'user_not_found';
            return logFailedLogin($U, $AREA, $KEY, $VALUE, $reason);
        }

        $passwordMissing = trim((string) ($U->password ?? '')) === '';
        if ($passwordMissing) {
            $ALERT = 924;
            $reason = 'partial_registration_password_missing';
            return logFailedLogin($U, $AREA, $KEY, $VALUE, $reason);
        }

        if (!checkPassword($PASSWORD, $U->password)) {
            $ALERT = 905;
            $reason = 'invalid_password';
            return logFailedLogin($U, $AREA, $KEY, $VALUE, $reason);
        }

        if ($U->deleted) {
            $ALERT = 912;
            $reason = 'user_deleted';
            return logFailedLogin($U, $AREA, $KEY, $VALUE, $reason);
        }

        if (!$U->active) {
            $ALERT = 909;
            $reason = 'user_inactive';
            return logFailedLogin($U, $AREA, $KEY, $VALUE, $reason);
        }

        if (!in_array($AREA, $U->area, true)) {
            $ALERT = 911;
            $reason = 'area_not_allowed';
            return logFailedLogin($U, $AREA, $KEY, $VALUE, $reason);
        }

        if (!isPermitAllowed($PERMIT_REQUIRED, $U->authority)) {
            $ALERT = 915;
            $reason = 'permit_not_allowed';
            return logFailedLogin($U, $AREA, $KEY, $VALUE, $reason);
        }

        $EMAIL_VERIFICATION = userLoginEmailVerificationConfig($U, $AREA, $PERMIT_REQUIRED);
        $emailVerified = isUserEmailVerified((int) $U->id);

        if (($EMAIL_VERIFICATION['required'] ?? false) && !$emailVerified) {
            $MAIL_RESULT = userSendEmailVerificationMail((int) $U->id, (string) $U->email, $EMAIL_VERIFICATION);
            $sentLink = trim((string) ($MAIL_RESULT->sent_link ?? ($EMAIL_VERIFICATION['sent_link'] ?? '')));

            $logMeta = [
                'authority' => $EMAIL_VERIFICATION['authority'] ?? '',
                'flow' => (string) ($MAIL_RESULT->flow ?? ($EMAIL_VERIFICATION['flow'] ?? 'login')),
                'requested_from_url' => (string) ($MAIL_RESULT->requested_from_url ?? ($EMAIL_VERIFICATION['requested_from_url'] ?? '')),
                'email_sent' => (bool) ($MAIL_RESULT->sent ?? false),
                'verification_sent_link' => $sentLink,
            ];

            if (!($MAIL_RESULT->success ?? false)) {
                $ALERT = (int) ($MAIL_RESULT->alert_code ?? $MAIL_RESULT->alert ?? 908);
                $reason = 'email_verification_mail_failed';
                $logMeta['error'] = (string) ($MAIL_RESULT->message ?? '');
                return logFailedLogin($U, $AREA, $KEY, $VALUE, $reason, $logMeta);
            }

            if (($MAIL_RESULT->already_verified ?? false) === true) {
                $syncResult = markUserEmailVerified((int) $U->id);

                if (!($syncResult->success ?? false)) {
                    $ALERT = (int) ($syncResult->alert_code ?? 900);
                    $reason = 'email_verification_state_sync_failed';
                    $logMeta['already_verified'] = true;
                    $logMeta['error'] = (string) ($syncResult->message ?? '');
                    return logFailedLogin($U, $AREA, $KEY, $VALUE, $reason, $logMeta);
                }
            } else {
                $ALERT = 924;
                $reason = 'partial_registration_email_not_verified';
                logFailedLogin($U, $AREA, $KEY, $VALUE, $reason, $logMeta);

                if ($sentLink !== '') {
                    header("Location: $sentLink");
                    exit;
                }

                return false;
            }
        }

        $_SESSION['user_id'] = $U->id;

        Wonder\Auth\RememberMe::set($U->id, $AREA);
        Wonder\Auth\AuthLog::write('login_success', (int) $U->id, $AREA, true, [
            'key' => $KEY,
            'value' => $VALUE,
        ]);

        return true;
        
    }

    function isPermitAllowed($PERMIT_REQUIRED, array $userAuthorities): bool
    {
        if ($PERMIT_REQUIRED === null) {
            return true;
        }

        if (is_array($PERMIT_REQUIRED)) {
            return count(array_intersect($PERMIT_REQUIRED, $userAuthorities)) > 0;
        }

        return in_array($PERMIT_REQUIRED, $userAuthorities, true);
    }

    function logFailedLogin($U, $AREA, $KEY, $VALUE, $reason, array $extraMeta = []): bool
    {
        $meta = [
            'key' => $KEY,
            'value' => $VALUE,
            'reason' => $reason,
        ];

        if (!empty($extraMeta)) {
            $meta = array_merge($meta, $extraMeta);
        }

        Wonder\Auth\AuthLog::write(
            'login_failed',
            $U->exists ? (int) $U->id : null,
            $AREA,
            false,
            $meta
        );

        return false;
    }

    // Verifica utente senza login
    function verifyUser($KEY, $VALUE, $AREA = null, $PERMIT_REQUIRED = null) {

        global $ALERT;

        $VALUE = sanitize($VALUE);

        $U = infoUser($VALUE, $KEY);

        $RETURN = (object) array();
        $RETURN->user = $U;
        $RETURN->response = false;

        if ($U->exists) {
            if (!$U->deleted) {
                if ($U->active) {
                    if ($AREA == null || in_array($AREA, $U->area)) {
                        if ($PERMIT_REQUIRED == null || in_array($PERMIT_REQUIRED, $U->authority)) {
                            $RETURN->response = true;
                        } else {
                            $ALERT = 915;
                        }
                    } else {
                        $ALERT = 911;
                    }
                } else {
                    $ALERT = 909;
                }
            } else {
                $ALERT = 912;
            }
        } else {
            $ALERT = 901;   
        }

        return $RETURN;

    }

    // Logout utente e pulizia sessione/cookie
    function logoutUser(string $AREA): void
    {

        $userId = $_SESSION['user_id'] ?? null;
        if (!empty($userId)) {
            Wonder\Auth\AuthLog::write('logout', (int) $userId, $AREA, true);
        }

        Wonder\Auth\RememberMe::clear($AREA);

        $_SESSION = [];

        if (session_id() !== '') {
            session_regenerate_id(true);
            session_destroy();
        }

        if (session_name()) {
            \Wonder\Http\Cookie::clear(session_name());
        }

    }
