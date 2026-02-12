<?php

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
                if ($U->deleted == 'false') {
                    if ($U->active == 'true'){
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
    function authenticateUser($KEY, $VALUE, $PASSWORD, $AREA, $PERMIT_REQUIRED = null) {

        global $ALERT;

        $VALUE = sanitize($VALUE);
        $PASSWORD = sanitize($PASSWORD);

        $U = infoUser($VALUE, $KEY);

        $reason = '';

        if ($U->exists) {
            if (checkPassword($PASSWORD, $U->password)){
                if ($U->deleted == 'false') {
                    if ($U->active == 'true') {
                        if (in_array($AREA, $U->area)) {
                            if ($PERMIT_REQUIRED == null || in_array($PERMIT_REQUIRED, $U->authority)) {
                                $_SESSION['user_id'] = $U->id;
                                Wonder\Auth\RememberMe::set($U->id, $AREA);
                                Wonder\Auth\AuthLog::write('login_success', (int) $U->id, $AREA, true, [
                                    'key' => $KEY,
                                    'value' => $VALUE
                                ]);
                                return true;
                            } else {
                                $ALERT = 915;
                                $reason = 'permit_not_allowed';
                            }
                        } else {
                            $ALERT = 911;
                            $reason = 'area_not_allowed';
                        }
                    } else {
                        $ALERT = 909;
                        $reason = 'user_inactive';
                    }
                } else {
                    $ALERT = 912;
                    $reason = 'user_deleted';
                }
            } else {
                $ALERT = 905; 
                $reason = 'invalid_password';
            }
        } else {
            $ALERT = ($KEY == 'email') ? 904 : 901;
            $reason = 'user_not_found';
        }

        Wonder\Auth\AuthLog::write('login_failed', $U->exists ? (int) $U->id : null, $AREA, false, [
            'key' => $KEY,
            'value' => $VALUE,
            'reason' => $reason
        ]);
        
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
            if ($U->deleted == 'false') {
                if ($U->active == 'true') {
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
