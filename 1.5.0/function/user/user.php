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

        $RETURN->area = isset($RETURN->area) ? json_decode($RETURN->area, true) : [];
        $RETURN->area = empty($RETURN->area) ? [] : $RETURN->area;

        $RETURN->authority = isset($RETURN->authority) ? json_decode($RETURN->authority, true) : [];
        $RETURN->authority = empty($RETURN->authority) ? [] : $RETURN->authority;

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

        $RETURN->fullName = isset($RETURN->name) ? $RETURN->name.' '.$RETURN->surname : '';
        $RETURN->prettyCreation = isset($RETURN->creation) ? date('d/m/Y', strtotime($RETURN->creation)).' alle '.date('H:i', strtotime($RETURN->creation)) : '';

        return $RETURN;

    }

    function user($POST, $MODIFY_ID = null) {

        global $ALERT;
        global $PATH;
        global $TABLE;

        // Crea o modifica un utente e gestisce gli hook legati alla authority.
        // Prepara oggetti di ritorno e payload.
        $RETURN = (object) [];
        $UPLOAD = [];
        
        // Definisce i campi protetti che non devono essere copiati direttamente dal POST.
        $PROTECTED_COLUMNS = ['name', 'surname', 'email', 'username', 'password', 'profile_picture', 'color', 'area', 'authority' ];

        // Copia nel payload solo colonne presenti in tabella e non protette.
        foreach ($POST as $column => $value) {
            if (!in_array($column,$PROTECTED_COLUMNS) && sqlColumnExists('user', $column)) {
                $UPLOAD[$column] = $value;
            }
        }

        // Normalizza i campi base.
        if (isset($POST['name'])) { $UPLOAD['name'] = sanitizeFirst($POST['name']); }
        if (isset($POST['surname'])) { $UPLOAD['surname'] = sanitizeFirst($POST['surname']); }
        if (isset($POST['active'])) { $UPLOAD['active'] = $POST['active']; }
        
        // Email: sanitizzazione e controllo di unicita.
        if (isset($POST['email'])) { 
            $UPLOAD['email'] = sanitize(strtolower($POST['email'])); 
            if (!unique($POST['email'], 'user', 'email', $MODIFY_ID)) { $ALERT = 906; }
        }
        
        // Username: sanitizzazione e controllo di unicita.
        if (isset($POST['username'])) { 
            $UPLOAD['username'] = sanitize(strtolower($POST['username']));
            if (!unique($POST['username'], 'user', 'username', $MODIFY_ID)) { $ALERT = 907; } 
        }

        // Upload foto profilo secondo le regole configurate.
        if (isset($POST['profile_picture'])) { 
            $RULES = isset($TABLE->USER['profile_picture']['input']['format']) ? $TABLE->USER['profile_picture']['input']['format'] : [];
            $UPLOAD['profile_picture'] = uploadFiles($POST['profile_picture'], $RULES, $PATH->rUpload.'/user', []);
        }

        // Normalizza il colore se presente.
        if (isset($POST['color'])) { 
            $UPLOAD['color'] = strtolower($POST['color']);
        }

        // Risolve la permission associata alla authority, se presente.
        $PERMISSION = null;
        if (isset($POST['authority'])) { $PERMISSION = permissions($POST['authority']); }

        if ($MODIFY_ID == null) {

            // Flusso di creazione.
            // Prepara liste authority e area.
            $authority = [];
            $area = [];

            // Popola authority/area dai valori passati.
            if (isset($POST['authority'])) { array_push($authority, $POST['authority']); }
            if (isset($POST['area'])) { array_push($area, $POST['area']); }

            // Salva authority/area nel payload in formato JSON (compatibilita legacy).
            $UPLOAD['authority'] = json_encode($authority);
            $UPLOAD['area'] = json_encode($area);

            // Genera username di default e cifra la password se presente.
            if (!isset($UPLOAD['username']) || empty($UPLOAD['username'])) { $UPLOAD['username'] = create_link(substr($UPLOAD['email'], 0, strpos($UPLOAD['email'], '@')), 'user', 'username'); }
            if (isset($POST['password'])) { $UPLOAD['password'] = hashPassword($POST['password']); }

            // Validazione authority prima di scrivere dati su DB.
            if (empty($ALERT) && $PERMISSION && !empty($PERMISSION->functionValidate)) {

                $AUTHORITY_VALIDATE = call_user_func_array($PERMISSION->functionValidate, [$POST, $UPLOAD, null, $MODIFY_ID]);

                // La validazione puo aggiornare il POST (normalizzazioni o default).
                if (is_object($AUTHORITY_VALIDATE) && isset($AUTHORITY_VALIDATE->post) && is_array($AUTHORITY_VALIDATE->post)) {
                    $POST = array_merge($POST, $AUTHORITY_VALIDATE->post);
                }

                // Flag per evitare doppia validazione negli hook di authority.
                if (isset($POST['authority'])) { $POST['_' . $POST['authority'] . '_validated'] = true; }

            }

            if (empty($ALERT)) { 

                // Inserisce il record utente.
                $sql = sqlInsert('user', $UPLOAD); 

                // Prepara il ritorno base.
                $RETURN->user = infoUser($sql->insert_id);
                $RETURN->values = $UPLOAD;

                // Hook di creazione per authority specifica.
                if ($PERMISSION && !empty($PERMISSION->functionCreation)) {
                    
                    $AUTHORITY_UPLOAD = call_user_func_array($PERMISSION->functionCreation, [$POST, $UPLOAD, $RETURN->user, $MODIFY_ID]);
                    $RETURN->values = array_merge($AUTHORITY_UPLOAD->values, $UPLOAD);
                    $RETURN->user = $AUTHORITY_UPLOAD->user;

                }

            } else {

                // In caso di errori, ritorna utente vuoto e dati originali.
                $RETURN->user = infoUser('');
                $RETURN->values = $POST;

            }

        } else {

            // Flusso di modifica.
            // Carica utente esistente.
            $M_USER = infoUser($MODIFY_ID);

            // Validazione authority prima della modifica.
            if (empty($ALERT) && $PERMISSION && !empty($PERMISSION->functionValidate)) {

                $AUTHORITY_VALIDATE = call_user_func_array($PERMISSION->functionValidate, [$POST, $UPLOAD, $M_USER, $MODIFY_ID]);

                // La validazione puo aggiornare il POST (normalizzazioni o default).
                if (is_object($AUTHORITY_VALIDATE) && isset($AUTHORITY_VALIDATE->post) && is_array($AUTHORITY_VALIDATE->post)) {
                    $POST = array_merge($POST, $AUTHORITY_VALIDATE->post);
                }

                // Flag per evitare doppia validazione negli hook di authority.
                if (isset($POST['authority'])) { $POST['_' . $POST['authority'] . '_validated'] = true; }

            }

            // Recupera area e authority attuali.
            $area = $M_USER->area;
            $authority = $M_USER->authority;
            
            // Aggiunge area se non presente.
            if (isset($POST['area']) && !in_array($POST['area'], $area)) { array_push($area, $POST['area']); }

            if (isset($POST['area']) && ($POST['area'] == 'backend' || $POST['area'] == 'api')) {

                # Se stai assegnando un permesso nell'area backend o api
                # Se il permesso fa parte dei permessi concessi
                # Elimina tutti i permessi backend o api già presenti e aggiungi quello nuovo
                # Ogni utente può avere solo un permesso per l'area backend o api

                // Backend/API: mantiene un solo permesso per area.
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

                // Frontend: consente permessi multipli.
                if (isset($POST['authority']) && !in_array($POST['authority'], $authority)) { array_push($authority, $POST['authority']); }

            }

            // Salva authority/area nel payload in formato JSON (compatibilita legacy).
            $UPLOAD['authority'] = json_encode($authority);
            $UPLOAD['area'] = json_encode($area);

            if (empty($ALERT)) { 
                
                // Aggiorna il record utente.
                sqlModify('user', $UPLOAD, 'id', $MODIFY_ID); 
            
                // Prepara il ritorno base.
                $RETURN->user = infoUser($M_USER->id);
                $RETURN->values = $UPLOAD;
               
                // Hook di modifica per authority specifica.
                if ($PERMISSION && !empty($PERMISSION->functionModify)) {
                    
                    $AUTHORITY_UPLOAD = call_user_func_array($PERMISSION->functionModify, [$POST, $UPLOAD, $RETURN->user, $MODIFY_ID]);
                    $RETURN->values = array_merge($AUTHORITY_UPLOAD->values, $UPLOAD);
                    $RETURN->user = $AUTHORITY_UPLOAD->user;

                }

            } else {

                // In caso di errori, ritorna utente vuoto e dati originali.
                $RETURN->user = infoUser('');
                $RETURN->values = $POST;

            };

        }

        // Ritorna utente e valori elaborati.
        return $RETURN;
        
    }
