<?php

    function infoUser($value, $filter = 'id') {

        global $PATH;
        global $DEFAULT;

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

        if (empty($RETURN->color)) {
            $RETURN->color = $DEFAULT->colorUser['blue']['color'];
            $RETURN->colorContrast = $DEFAULT->colorUser['blue']['contrast'];
        } else {
            $color = $RETURN->color;
            $RETURN->color = $DEFAULT->colorUser[$color]['color'];
            $RETURN->colorContrast = $DEFAULT->colorUser[$color]['contrast'];
        }

        $RETURN->profile_picture = empty($RETURN->profile_picture) ? "" : (array) json_decode($RETURN->profile_picture);

        if (empty($RETURN->profile_picture)) {

            $firstLetterName = empty($RETURN->name) ? "" : substr($RETURN->name, 0, 1);
            $firstLetterSurname = empty($RETURN->name) ? "" : substr($RETURN->surname, 0, 1);

            $image = "<div><div class='position-absolute top-50 start-50 translate-middle text-center'>$firstLetterName$firstLetterSurname</div></div>";

        } else {
            $image = "<img class='position-absolute top-50 start-50 translate-middle w-100 h-100' src='$PATH->upload/user/profile-picture/960x960-{$RETURN->profile_picture[0]}' alt='$RETURN->username'>";
        }

        $RETURN->avatar = "<div class='ratio ratio-1x1 rounded-circle border overflow-hidden' style='background: $RETURN->color !important;color: $RETURN->colorContrast !important;' >$image</div>";

        $RETURN->area = isset($RETURN->area) ? json_decode($RETURN->area, true) : [];
        $RETURN->fullName = isset($RETURN->name) ? $RETURN->name.' '.$RETURN->surname : '';
        $RETURN->prettyCreation = isset($RETURN->creation) ? date('d/m/Y', strtotime($RETURN->creation)).' alle '.date('H:i', strtotime($RETURN->creation)) : '';

        return $RETURN;

    }

    function user($POST, $MODIFY_ID = null) {

        global $ALERT;
        global $PATH;
        global $TABLE;

        $RETURN = (object) array();
        $UPLOAD = [];
        
        $PROTECTED_COLUMNS = ['name', 'surname', 'email', 'username', 'password', 'profile_picture', 'color', 'area', 'authority' ];

        foreach ($POST as $column => $value) {
            if (!in_array($column,$PROTECTED_COLUMNS) && sqlColumnExists('user', $column)) {
                $UPLOAD[$column] = $value;
            }
        }

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

        if (isset($POST['profile_picture'])) { 
            $RULES = isset($TABLE->USER['profile_picture']['input']['format']) ? $TABLE->USER['profile_picture']['input']['format'] : [];
            $UPLOAD['profile_picture'] = uploadFiles($POST['profile_picture'], $RULES, $PATH->rUpload.'/user', []);
        }

        if (isset($POST['color'])) { 
            $UPLOAD['color'] = strtolower($POST['color']);
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
                $RETURN->values = $POST;

            }

        } else {

            $M_USER = infoUser($MODIFY_ID);

            $area = $M_USER->area;
            $authority = $M_USER->authority;
            
            if (isset($POST['area']) && !in_array($POST['area'], $area)) { array_push($area, $POST['area']); }

            if (isset($POST['area']) && ($POST['area'] == 'backend' || $POST['area'] == 'api')) {

                # Se stai assegnando un permesso nell'area backend o api
                # Se il permesso fa parte dei permessi concessi
                # Elimina tutti i permessi backend o api già presenti e aggiungi quello nuovo
                # Ogni utente può avere solo un permesso per l'area backend o api

                if (isset($POST['authority']) && !in_array($POST['authority'], $authority)) { 
                    
                    $new_authority = [];

                    foreach ($authority as $k => $v) {
                        if (permissions($v)->area != $POST['area']) {
                            array_push($new_authority, $v);
                        }
                    }

                    array_push($new_authority, $POST['authority']);
                    
                    $authority = $new_authority;

                }

            } else if (isset($POST['area']) && $POST['area'] == 'frontend') {

                # Se stai assegnando un permesso nell'area frontend
                # Se il permesso fa parte dei permessi concessi
                # Ogni utente può avere più permessi per l'area frontend

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
                $RETURN->values = $POST;

            };

        }

        return $RETURN;
        
    }
