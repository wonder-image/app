<?php

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

    function authenticateUser($KEY, $VALUE, $PASSWORD, $AREA, $PERMIT_REQUIRED = null) {

        global $ALERT;

        $VALUE = sanitize($VALUE);
        $PASSWORD = sanitize($PASSWORD);

        $U = infoUser($VALUE, $KEY);

        if ($U->exists) {
            if (checkPassword($PASSWORD, $U->password)){
                if ($U->deleted == 'false') {
                    if ($U->active == 'true') {
                        if (in_array($AREA, $U->area)) {
                            if ($PERMIT_REQUIRED == null || in_array($PERMIT_REQUIRED, $U->authority)) {
                                $_SESSION['user_id'] = $U->id;
                                return true;
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
                $ALERT = 905; 
            }
        } else {
            $ALERT = ($KEY == 'email') ? 904 : 901;
        }
        
    }

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
