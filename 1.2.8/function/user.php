<?php

    function infoUser($value, $filter = 'id') {

        if ($filter == 'id') {
            $SQL = sqlSelect('user', [$filter => $value], 1);
        } else {
            $SQL = sqlSelect('user', [$filter => $value, 'deleted' => 'false'], 1);
        }
        
        $RETURN = (object) array();
        $RETURN->exists = $SQL->exists;
        foreach ($SQL->row as $column => $value) { $RETURN->$column = isset($value) ? $value : ''; }

        $RETURN->authority = isset($RETURN->authority) ? json_decode($RETURN->authority, true) : [];

        foreach ($RETURN->authority as $key => $authority) {
            if (!empty(permissions($authority)->functionInfo)) {
                $RETURN->$authority = call_user_func_array(permissions($authority)->functionInfo, [ $RETURN->id ]);
            }
        }

        $RETURN->area = isset($RETURN->area) ? json_decode($RETURN->area, true) : [];
        $RETURN->fullName = isset($RETURN->name) ? $RETURN->name.' '.$RETURN->surname : '';
        $RETURN->prettyCreation = isset($RETURN->creation) ? date('d/m/Y', strtotime($RETURN->creation)).' alle '.date('H:i', strtotime($RETURN->creation)) : '';

        return $RETURN;

    }

    function user($POST, $MODIFY_ID = null) {

        global $ALERT;

        $RETURN = (object) array();
        $UPLOAD = [];

        if (isset($POST['name'])) { $UPLOAD['name'] = sanitizeFirst($POST['name']); }
        if (isset($POST['surname'])) { $UPLOAD['surname'] = sanitizeFirst($POST['surname']); }
        if (isset($POST['active'])) { $UPLOAD['active'] = $POST['active']; }
        
        if (isset($POST['email'])) { 
            $UPLOAD['email'] = sanitize(strtolower($POST['email'])); 
            if (!unique($POST['email'], 'user', 'email', $MODIFY_ID)) { $ALERT = 906; }
        }
        
        if (isset($POST['username'])) { 
            $UPLOAD['username'] = sanitize(strtolower($POST['username']));
            if (!unique($POST['username'], 'user', 'username', $MODIFY_ID)) { $ALERT = 907; } 
        }

        if ($MODIFY_ID == null) {

            $authority = [];
            $area = [];

            if (isset($POST['authority'])) { array_push($authority, $POST['authority']); }
            if (isset($POST['area'])) { array_push($area, $POST['area']); }

            $UPLOAD['authority'] = json_encode($authority);
            $UPLOAD['area'] = json_encode($area);

            if (!isset($UPLOAD['username']) || empty($UPLOAD['username'])) { $UPLOAD['username'] = create_link(substr($UPLOAD['email'], 0, strpos($UPLOAD['email'], '@')), 'user', 'username'); }
            if (isset($POST['password'])) { $UPLOAD['password'] = hashPassword($POST['password']); }

            if (empty($ALERT)) { 

                $sql = sqlInsert('user', $UPLOAD); 

                $RETURN->user = infoUser($sql->insert_id);
                $RETURN->values = $UPLOAD;

                if (isset($POST['authority']) && !empty(permissions($POST['authority'])->functionCreation)) {
                    
                    $AUTHORITY_UPLOAD = call_user_func_array(permissions($POST['authority'])->functionCreation, [$POST, $UPLOAD, $RETURN->user, $MODIFY_ID]);
                    $RETURN->values = array_merge($AUTHORITY_UPLOAD->values, $UPLOAD);
                    $RETURN->user = $AUTHORITY_UPLOAD->user;

                }

            } else {

                $RETURN->user = infoUser('');
                $RETURN->values = $UPLOAD;

            }

        } else {

            $M_USER = infoUser($MODIFY_ID);

            $area = $M_USER->area;
            $authority = $M_USER->authority;
            
            if (isset($POST['area']) && !in_array($POST['area'], $area)) { array_push($area, $POST['area']); }

            if (isset($POST['area']) && $POST['area'] == 'backend') {
                if (isset($POST['authority']) && !in_array($POST['authority'], $authority)) { 
                    
                    $new_authority = [];

                    foreach ($authority as $k => $v) {
                        if (permissions($v)->area != 'backend') {
                            array_push($new_authority, $v);
                        }
                    }

                    array_push($new_authority, $POST['authority']);
                    
                    $authority = $new_authority;

                }
            } else if (isset($POST['area']) && $POST['area'] == 'frontend') {
                if (isset($POST['authority']) && !in_array($POST['authority'], $authority)) { array_push($authority, $POST['authority']); }
            }

            $UPLOAD['authority'] = json_encode($authority);
            $UPLOAD['area'] = json_encode($area);

            if (empty($ALERT)) { 
                
                sqlModify('user', $UPLOAD, 'id', $MODIFY_ID); 
            
                $RETURN->user = infoUser($M_USER->id);
                $RETURN->values = $UPLOAD;
               
                if (isset($POST['authority']) && !empty(permissions($POST['authority'])->functionModify)) {
                    
                    $AUTHORITY_UPLOAD = call_user_func_array(permissions($POST['authority'])->functionModify, [$POST, $UPLOAD, $RETURN->user, $MODIFY_ID]);
                    $RETURN->values = array_merge($AUTHORITY_UPLOAD->values, $UPLOAD);
                    $RETURN->user = $AUTHORITY_UPLOAD->user;

                }

            } else {

                $RETURN->user = infoUser('');
                $RETURN->values = $UPLOAD;

            };

        }

        return $RETURN;
        
    }

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

    function permissions($PERMIT = null) {

        global $PERMITS;

        if ($PERMIT == null) {

            $RETURN = [];

            foreach ($PERMITS as $area => $array) {
                foreach ($array as $key => $value) { if ($key != 'links') { $RETURN[$key] = $value['name']; } }
            }

        } else {

            if (isset($PERMITS['backend'][$PERMIT])) {
                $AREA = 'backend';
            } elseif (isset($PERMITS['frontend'][$PERMIT])) {
                $AREA = 'frontend';
            }

            $ARRAY = $PERMITS[$AREA][$PERMIT];

            $login = isset($PERMITS[$AREA]['links']['login']) ? $PERMITS[$AREA]['links']['login'] : '';
            $signIn = isset($PERMITS[$AREA]['links']['sign-in']) ? $PERMITS[$AREA]['links']['sign-in'] : '';
            $passwordRecovery = isset($PERMITS[$AREA]['links']['password-recovery']) ? $PERMITS[$AREA]['links']['password-recovery'] : '';
            $passwordRestore = isset($PERMITS[$AREA]['links']['password-restore']) ? $PERMITS[$AREA]['links']['password-restore'] : '';
            $passwordSet = isset($PERMITS[$AREA]['links']['password-set']) ? $PERMITS[$AREA]['links']['password-set'] : '';

            $RETURN = (object) array();
            $RETURN->icon = isset($ARRAY['icon']) ? $ARRAY['icon'] : '';
            $RETURN->name = isset($ARRAY['name']) ? $ARRAY['name'] : '';
            $RETURN->bg = isset($ARRAY['bg']) ? $ARRAY['bg'] : '';
            $RETURN->tx = isset($ARRAY['tx']) ? $ARRAY['tx'] : '';
            $RETURN->color = isset($ARRAY['color']) ? $ARRAY['color'] : '';
            $RETURN->creator = isset($ARRAY['creator']) ? $ARRAY['creator'] : [];

            $RETURN->area = $AREA;

            $RETURN->login = isset($ARRAY['links']['login']) ? $ARRAY['links']['login'] : $login;
            $RETURN->signIn = isset($ARRAY['links']['sign-in']) ? $ARRAY['links']['sign-in'] : $signIn;
            $RETURN->passwordRecovery = isset($ARRAY['links']['password-recovery']) ? $ARRAY['links']['password-recovery'] : $passwordRecovery;
            $RETURN->passwordRestore = isset($ARRAY['links']['password-restore']) ? $ARRAY['links']['password-restore'] : $passwordRestore;
            $RETURN->passwordSet = isset($ARRAY['links']['password-set']) ? $ARRAY['links']['password-set'] : $passwordSet;

            $RETURN->functionCreation = isset($ARRAY['function']['creation']) ? $ARRAY['function']['creation'] : '';
            $RETURN->functionModify = isset($ARRAY['function']['modify']) ? $ARRAY['function']['modify'] : '';
            $RETURN->functionInfo = isset($ARRAY['function']['info']) ? $ARRAY['function']['info'] : '';

            $RETURN->badge = "<span class='badge $RETURN->bg $RETURN->tx'>$RETURN->name</span>";
            $RETURN->badgeIcon = "<span class='badge $RETURN->bg $RETURN->tx'>$RETURN->icon</span>";
            $RETURN->automaticResize = "<span class='phone-none badge $RETURN->bg $RETURN->tx'>$RETURN->name</span><span class='pc-none badge $RETURN->bg $RETURN->tx'>$RETURN->icon</span>";

        }

        return $RETURN;

    }

    function permissionsBackend($PERMIT = null) {

        global $PERMITS;

        if ($PERMIT == null) {

            $RETURN = [];

            foreach ($PERMITS['backend'] as $key => $value) {
                if ($key != 'links') { $RETURN[$key] = $value['name']; }
            }

        } else {
            
            $RETURN = permissions($PERMIT);
            if ($RETURN->area != 'backend') { $RETURN = ""; }

        }

        return $RETURN;

    }

    function permissionsFrontend($PERMIT = null) {

        global $PERMITS;

        if ($PERMIT == null) {

            $RETURN = [];

            foreach ($PERMITS['frontend'] as $key => $value) {
                if ($key != 'links') { $RETURN[$key] = $value['name']; }
            }

        } else {
            
            $RETURN = permissions($PERMIT);
            if ($RETURN->area != 'frontend') { $RETURN = ""; }

        }

        return $RETURN;

    }

?>