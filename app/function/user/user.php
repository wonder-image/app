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

        $RETURN->active = filter_var($RETURN->active ?? false, FILTER_VALIDATE_BOOLEAN);
        $RETURN->email_verified = filter_var($RETURN->email_verified ?? false, FILTER_VALIDATE_BOOLEAN);
        $RETURN->deleted = filter_var($RETURN->deleted ?? true, FILTER_VALIDATE_BOOLEAN);

        return $RETURN;

    }

    // Configurazione verifica email per il flusso richiesto.
    function userPermissionEmailVerificationConfig($PERMISSION, array $rules, string $flow = 'default'): array
    {

        global $PAGE;

        $wildcardRule = isset($rules['*']) && is_array($rules['*']) ? $rules['*'] : [];
        $emailRule = isset($rules['email']) && is_array($rules['email']) ? $rules['email'] : [];
        $flow = userEmailVerificationNormalizeFlow($flow);

        $tokenLink = trim((string) ($emailRule['token_link'] ?? $wildcardRule['token_link'] ?? ''));
        $sentLink = trim((string) ($emailRule['sent_link'] ?? $wildcardRule['sent_link'] ?? ''));
        $ttlHours = (int) ($emailRule['ttl_hours'] ?? $wildcardRule['ttl_hours'] ?? 24);

        $redirectBase64 = '';
        $requestedFromUrl = '';
        if (isset($PAGE) && is_object($PAGE) && isset($PAGE->redirectBase64)) {
            $redirectBase64 = userEmailVerificationNormalizeRedirectBase64((string) $PAGE->redirectBase64);
        }
        if (isset($PAGE) && is_object($PAGE) && isset($PAGE->url)) {
            $requestedFromUrl = userEmailVerificationNormalizeRequestedFromUrl((string) $PAGE->url);
        }
        if ($requestedFromUrl === '' && isset($_SERVER['REQUEST_URI'])) {
            $requestedFromUrl = userEmailVerificationNormalizeRequestedFromUrl((string) $_SERVER['REQUEST_URI']);
        }

        if ($ttlHours <= 0) {
            $ttlHours = 24;
        }
        if ($redirectBase64 !== '' && $sentLink !== '') {
            $sentLink = (new Wonder\Http\UrlParser($sentLink))->addParameter('redirect', $redirectBase64);
        }

        return [
            'required' => userVerificationRuleRequired($emailRule) || userVerificationRuleRequired($wildcardRule),
            'token_link' => $tokenLink,
            'sent_link' => $sentLink,
            'flow' => $flow,
            'redirect_base64' => $redirectBase64,
            'requested_from_url' => $requestedFromUrl,
            'ttl_hours' => $ttlHours,
        ];

    }

    // Genera token/link e invia la mail di verifica.
    function userSendEmailVerificationMail(int $userId, string $userEmail, array $config): object
    {

        global $SOCIETY;

        $tokenLink = trim((string) ($config['token_link'] ?? ''));
        $sentLink = trim((string) ($config['sent_link'] ?? ''));
        $flow = userEmailVerificationNormalizeFlow((string) ($config['flow'] ?? 'default'));
        $redirectBase64 = userEmailVerificationNormalizeRedirectBase64((string) ($config['redirect_base64'] ?? ''));
        $requestedFromUrl = userEmailVerificationNormalizeRequestedFromUrl((string) ($config['requested_from_url'] ?? ''));
        $ttlHours = (int) ($config['ttl_hours'] ?? 24);
        $baseResult = [
            'sent' => false,
            'sent_link' => $sentLink,
            'flow' => $flow,
            'redirect_base64' => $redirectBase64,
            'requested_from_url' => $requestedFromUrl,
        ];

        if ($tokenLink === '') {
            return userVerificationFailure(900, $baseResult);
        }

        $payload = prepareUserEmailVerificationEmail(
            $userId,
            $tokenLink,
            $ttlHours,
            [
                'flow' => $flow,
                'redirect_base64' => $redirectBase64,
                'requested_from_url' => $requestedFromUrl,
            ]
        );

        if (($payload->already_verified ?? false) === true) {
            return (object) [
                'success' => true,
                'sent' => false,
                'already_verified' => true,
                'sent_link' => $baseResult['sent_link'],
                'verification_url' => '',
                'flow' => $flow,
                'redirect_base64' => $redirectBase64,
                'requested_from_url' => $requestedFromUrl,
            ];
        }

        if (!($payload->success ?? false)) {
            return userVerificationFailure((int) ($payload->alert_code ?? $payload->alert ?? 900), $baseResult);

        }

        $subject = (string) ($payload->email_subject ?? __t('emails.email_verification.subject'));
        $body = (string) ($payload->email_html ?? $payload->email_text ?? '');
        $from = isset($SOCIETY->email) ? (string) $SOCIETY->email : '';
        $sent = sendMail($from, $userEmail, $subject, $body);

        if (!$sent) {
            return userVerificationFailure(908, $baseResult + [
                'verification_url' => (string) ($payload->verification_url ?? ''),
            ]);
        }

        return (object) [
            'success' => true,
            'sent' => true,
            'already_verified' => false,
            'sent_link' => $sentLink,
            'verification_url' => (string) ($payload->verification_url ?? ''),
            'expires_at' => (string) ($payload->expires_at ?? ''),
            'token_id' => (int) ($payload->token_id ?? 0),
            'alert_code' => null,
            'flow' => $flow,
            'redirect_base64' => $redirectBase64,
            'requested_from_url' => $requestedFromUrl,
        ];

    }

    // Verifica se il payload contiene almeno una scelta consenso.
    function userHasConsentPayload(array $post): bool
    {

        foreach ($post as $field => $value) {
            if (is_string($field) && str_starts_with($field, 'accept_')) {
                return true;
            }
        }

        return false;

    }

    // Costruisce il contesto tecnico da salvare sugli eventi consenso.
    function userConsentContextFromPost(array $post): array
    {

        $context = [
            'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'locale' => (string) (__l() ?: 'it'),
            'source' => (string) ($post['consent_source'] ?? 'web'),
            'ui_surface' => (string) ($post['consent_ui_surface'] ?? 'signup'),
        ];

        $required = $post['required_document_types'] ?? ($post['consent_required_document_types'] ?? null);
        if (is_string($required)) {
            $decoded = json_decode($required, true);
            $required = is_array($decoded) ? $decoded : array_map('trim', explode(',', $required));
        }
        if (is_array($required)) {
            $required = array_values(array_filter(array_map(static fn($v) => trim((string) $v), $required)));
            if (count($required) > 0) {
                $context['required_document_types'] = $required;
            }
        }

        $evidence = $post['evidence_json'] ?? ($post['consent_evidence_json'] ?? null);
        if (is_string($evidence)) {
            $decoded = json_decode($evidence, true);
            $evidence = is_array($decoded) ? $decoded : null;
        }
        if (is_array($evidence)) {
            $context['evidence_json'] = $evidence;
        }

        return $context;

    }

    function user($POST, $MODIFY_ID = null) {

        global $ALERT;
        global $PATH;

        // Crea o modifica un utente e gestisce gli hook legati alla authority.
        $RETURN = (object) [
            'verification_required' => false,
            'verification_checks' => [],
            'email_verification_required' => false,
            'email_verification_sent' => false,
            'email_verification_sent_link' => '',
            'email_verification_url' => '',
            'email_verification_already_verified' => false,
            'consents' => [],
            'already_registered' => false,
        ];

        $UPLOAD = [];

        // Definisce i campi protetti che non devono essere copiati direttamente dal POST.
        $PROTECTED_COLUMNS = [ 'name', 'surname', 'email', 'username', 'password', 'profile_picture', 'color', 'area', 'authority' ];

        // Copia nel payload solo colonne presenti in tabella e non protette.
        foreach ($POST as $column => $value) {
            if (!in_array($column, $PROTECTED_COLUMNS, true) && sqlColumnExists('user', $column)) {
                $UPLOAD[$column] = $value;
            }
        }

        // Normalizza i campi base.
        if (isset($POST['name'])) { $UPLOAD['name'] = sanitizeFirst($POST['name']); }
        if (isset($POST['surname'])) { $UPLOAD['surname'] = sanitizeFirst($POST['surname']); }
        if (isset($POST['active'])) { $UPLOAD['active'] = $POST['active']; }

        // Upload foto profilo secondo le regole configurate.
        if (isset($POST['profile_picture'])) {
            $USER_SCHEMA = \Wonder\App\Table::key('user')->schema();
            $RULES = isset($USER_SCHEMA['profile_picture']['input']['format']) ? $USER_SCHEMA['profile_picture']['input']['format'] : [];
            $UPLOAD['profile_picture'] = uploadFiles($POST['profile_picture'], $RULES, $PATH->rUpload.'/user', []);
        }

        // Normalizza il colore se presente.
        if (isset($POST['color'])) {
            $UPLOAD['color'] = strtolower($POST['color']);
        }

        // Risolve la permission associata alla authority, se presente.
        $PERMISSION = null;
        if (isset($POST['authority'])) { $PERMISSION = permissions($POST['authority']); }

        // Calcola verifiche richieste (es. email) dalla permission.
        $VERIFICATION_RULES = userPermissionVerificationRules($PERMISSION);
        $REQUIRED_VERIFICATIONS = userPermissionRequiredVerifications($VERIFICATION_RULES);
        $EMAIL_VERIFICATION = userPermissionEmailVerificationConfig($PERMISSION, $VERIFICATION_RULES, 'signup');

        $RETURN->verification_required = count($REQUIRED_VERIFICATIONS) >= 1;
        $RETURN->verification_checks = array_keys($REQUIRED_VERIFICATIONS);
        $RETURN->email_verification_required = (bool) $EMAIL_VERIFICATION['required'];
        $RETURN->email_verification_sent_link = (string) ($EMAIL_VERIFICATION['sent_link'] ?? '');

        $EXISTING_EMAIL_USER = null;
        $REUSE_EXISTING_USER = false;
        $SUBMITTED_PASSWORD = isset($POST['password']) ? (string) $POST['password'] : '';
        $HAS_SUBMITTED_PASSWORD = trim($SUBMITTED_PASSWORD) !== '';

        // Email: sanitizzazione e controllo di unicita.
        // Se è richiesta la verifica email, un account già esistente viene riutilizzato.
        if (isset($POST['email'])) {

            $UPLOAD['email'] = sanitize(strtolower($POST['email']));

            if ($MODIFY_ID == null && ($EMAIL_VERIFICATION['required'] ?? false)) {
                $EXISTING_EMAIL_USER = infoUser($UPLOAD['email'], 'email');
                $REUSE_EXISTING_USER = (bool) ($EXISTING_EMAIL_USER->exists ?? false);
                $RETURN->already_registered = $REUSE_EXISTING_USER;
            }

            if (!$REUSE_EXISTING_USER && !unique($UPLOAD['email'], 'user', 'email', $MODIFY_ID)) {
                $ALERT = 906;
            }

        }

        // Username: sanitizzazione e controllo di unicita.
        if (isset($POST['username'])) {
            $UPLOAD['username'] = sanitize(strtolower($POST['username']));
            if (!$REUSE_EXISTING_USER && !unique($UPLOAD['username'], 'user', 'username', $MODIFY_ID)) {
                $ALERT = 907;
            }
        }

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

            if ($REUSE_EXISTING_USER && is_object($EXISTING_EMAIL_USER) && ($EXISTING_EMAIL_USER->exists ?? false)) {

                $RETURN->user = infoUser($EXISTING_EMAIL_USER->id);
                $RETURN->values = $UPLOAD;

            } else {

                // Genera username di default e cifra la password se presente.
                if (!isset($UPLOAD['username']) || empty($UPLOAD['username'])) {
                    $usernameBase = isset($UPLOAD['email']) ? explode('@', (string) $UPLOAD['email'])[0] : code(8, 'letters');
                    $UPLOAD['username'] = create_link($usernameBase, 'user', 'username');
                }

                if ($HAS_SUBMITTED_PASSWORD) { $UPLOAD['password'] = hashPassword($SUBMITTED_PASSWORD); }

                // Validazione authority prima di scrivere dati su DB.
                if (empty($ALERT) && $PERMISSION && !empty($PERMISSION->functionValidate)) {

                    $AUTHORITY_VALIDATE = call_user_func_array($PERMISSION->functionValidate, [ $POST, $UPLOAD, null, $MODIFY_ID ]);

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

                        $AUTHORITY_UPLOAD = call_user_func_array($PERMISSION->functionCreation, [ $POST, $UPLOAD, $RETURN->user, $MODIFY_ID ]);
                        $RETURN->values = array_merge($AUTHORITY_UPLOAD->values, $UPLOAD);
                        $RETURN->user = $AUTHORITY_UPLOAD->user;

                    }

                } else {

                    // In caso di errori, ritorna utente vuoto e dati originali.
                    $RETURN->user = infoUser('');
                    $RETURN->values = $POST;

                }

            }

            // Registra i consensi in fase signup se presenti nel payload.
            if (empty($ALERT) && userHasConsentPayload($POST)) {

                $USER_ID = (int) ($RETURN->user->id ?? 0);

                if ($USER_ID <= 0) {

                    $ALERT = 900;

                } else {

                    try {
                        $RETURN->consents = registerUserConsents($USER_ID, $POST, userConsentContextFromPost($POST));
                    } catch (Throwable $exception) {
                        $ALERT = 900;
                        $RETURN->consents = [
                            'success' => false,
                            'message' => (string) $exception->getMessage(),
                        ];
                    }

                }

            }

            // Invia email di verifica sia a nuovo utente sia a utente già registrato.
            if (empty($ALERT) && ($EMAIL_VERIFICATION['required'] ?? false)) {

                $USER_ID = (int) ($RETURN->user->id ?? 0);
                $USER_EMAIL = (string) ($RETURN->user->email ?? ($UPLOAD['email'] ?? ''));

                if ($USER_ID <= 0 || $USER_EMAIL === '') {

                    $ALERT = 908;

                } else {

                    $MAIL_RESULT = userSendEmailVerificationMail($USER_ID, $USER_EMAIL, $EMAIL_VERIFICATION);

                    $RETURN->email_verification_sent = (bool) ($MAIL_RESULT->sent ?? false);
                    $RETURN->email_verification_url = (string) ($MAIL_RESULT->verification_url ?? '');
                    $RETURN->email_verification_already_verified = (bool) ($MAIL_RESULT->already_verified ?? false);
                    $RETURN->email_verification_sent_link = (string) ($MAIL_RESULT->sent_link ?? $RETURN->email_verification_sent_link);

                    if (!($MAIL_RESULT->success ?? false)) {
                        $ALERT = (int) ($MAIL_RESULT->alert_code ?? $MAIL_RESULT->alert ?? 908);
                        $RETURN->email_verification_error = (string) ($MAIL_RESULT->message ?? '');
                    }

                }

            }

        } else {

            // Flusso di modifica.
            // Carica utente esistente.
            $M_USER = infoUser($MODIFY_ID);

            // Validazione authority prima della modifica.
            if (empty($ALERT) && $PERMISSION && !empty($PERMISSION->functionValidate)) {

                $AUTHORITY_VALIDATE = call_user_func_array($PERMISSION->functionValidate, [ $POST, $UPLOAD, $M_USER, $MODIFY_ID ]);

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

            if ($HAS_SUBMITTED_PASSWORD && empty($M_USER->password)) { $UPLOAD['password'] = hashPassword($SUBMITTED_PASSWORD); }

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

                    $AUTHORITY_UPLOAD = call_user_func_array($PERMISSION->functionModify, [ $POST, $UPLOAD, $RETURN->user, $MODIFY_ID ]);
                    $RETURN->values = array_merge($AUTHORITY_UPLOAD->values, $UPLOAD);
                    $RETURN->user = $AUTHORITY_UPLOAD->user;

                }

                // Registra i consensi anche nel flusso di modifica cliente.
                if (userHasConsentPayload($POST)) {

                    $USER_ID = (int) ($RETURN->user->id ?? $M_USER->id ?? 0);

                    if ($USER_ID <= 0) {

                        $ALERT = 900;

                    } else {

                        try {
                            $RETURN->consents = registerUserConsents($USER_ID, $POST, userConsentContextFromPost($POST));
                        } catch (Throwable $exception) {
                            $ALERT = 900;
                            $RETURN->consents = [
                                'success' => false,
                                'message' => (string) $exception->getMessage(),
                            ];
                        }

                    }

                }

            } else {

                // In caso di errori, ritorna utente vuoto e dati originali.
                $RETURN->user = infoUser('');
                $RETURN->values = $POST;

            }

        }

        // Ritorna utente e valori elaborati.
        return $RETURN;

    }
