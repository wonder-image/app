<?php

    /**
     * Indica se l'email utente risulta verificata.
     */
    function isUserEmailVerified($userId): bool
    {

        $userId = (int) $userId;

        if ($userId <= 0) {
            return false;
        }

        $SQL = sqlSelect('user', [ 'id' => $userId ], 1);

        if (!$SQL->exists || !is_array($SQL->row)) {
            return false;
        }

        $verified = $SQL->row['email_verified'] ?? 0;
        $verifiedAt = (string) ($SQL->row['email_verified_at'] ?? '');

        if ($verified == '1' || $verified == 1 || $verified === true || $verified === 'true') {
            return true;
        }

        return $verifiedAt !== '' && $verifiedAt !== '0000-00-00 00:00:00';

    }

    /**
     * Marca email utente come verificata.
     */
    function markUserEmailVerified($userId, ?string $verifiedAt = null): object
    {

        $userId = (int) $userId;

        if ($userId <= 0) {
            return (object) [ 'success' => false, 'message' => 'user_id non valido' ];
        }

        $verifiedAt = $verifiedAt ?: date('Y-m-d H:i:s');

        $update = sqlModify('user', [
            'email_verified' => 1,
            'email_verified_at' => $verifiedAt,
        ], 'id', $userId);

        return (object) [
            'success' => (bool) ($update->success ?? false),
            'verified_at' => $verifiedAt,
            'query' => $update->query ?? '',
        ];

    }

    /**
     * Revoca flag verifica email (utile per test o procedure amministrative).
     */
    function unmarkUserEmailVerified($userId): object
    {

        $userId = (int) $userId;

        if ($userId <= 0) {
            return (object) [ 'success' => false, 'message' => 'user_id non valido' ];
        }

        $update = sqlModify('user', [
            'email_verified' => 0,
            'email_verified_at' => null,
        ], 'id', $userId);

        return (object) [
            'success' => (bool) ($update->success ?? false),
            'query' => $update->query ?? '',
        ];

    }

    /**
     * Genera payload completo (token + link + testo email) per verifica email.
     */
    function prepareUserEmailVerificationEmail(
        int $userId,
        string $verifyBaseUrl,
        ?string $continueRegistrationUrl = null,
        int $ttlHours = 24
    ): object {

        $tokenPayload = generateUserVerificationToken($userId, $continueRegistrationUrl, $ttlHours);

        if (!($tokenPayload->success ?? false)) {
            return $tokenPayload;
        }

        $verificationUrl = userEmailVerificationAppendQuery(
            $verifyBaseUrl,
            [
                'token' => (string) $tokenPayload->token,
                'lang' => (string) $tokenPayload->language_code,
            ]
        );

        $message = buildUserEmailVerificationMessage(
            $verificationUrl,
            (string) $tokenPayload->expires_at,
            isset($tokenPayload->continue_registration_url) ? (string) $tokenPayload->continue_registration_url : null
        );

        $tokenPayload->verification_url = $verificationUrl;
        $tokenPayload->email_subject = $message['subject'];
        $tokenPayload->email_text = $message['text'];
        $tokenPayload->email_html = $message['html'];

        return $tokenPayload;

    }

    /**
     * Genera il token di verifica utente (senza costruire il link).
     */
    function generateUserVerificationToken(
        int $userId,
        ?string $continueRegistrationUrl = null,
        int $ttlHours = 24
    ): object {

        $userId = (int) $userId;

        if ($userId <= 0) {
            return (object) [ 'success' => false, 'message' => 'user_id non valido' ];
        }

        if ($ttlHours <= 0) {
            $ttlHours = 24;
        }

        if ($ttlHours > 720) {
            $ttlHours = 720;
        }

        $userSql = sqlSelect('user', [ 'id' => $userId ], 1);
        if (!$userSql->exists) {
            return (object) [ 'success' => false, 'message' => "Utente non trovato: {$userId}" ];
        }

        if (isUserEmailVerified($userId)) {
            return (object) [ 'success' => false, 'message' => 'Email già verificata' ];
        }

        $languageCode = userEmailVerificationNormalizeLanguageCode();
        $continueRegistrationUrl = userEmailVerificationNormalizeContinueUrl($continueRegistrationUrl);

        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime($now.' +'.$ttlHours.' hours'));
        $token = userEmailVerificationGenerateToken();

        // Revoca eventuali token aperti già emessi per lo stesso utente.
        userEmailVerificationRevokeOpenTokens($userId, $now);

        $insert = sqlInsert('consent_confirmation_tokens', [
            'token_type' => userEmailVerificationTokenType(),
            'user_id' => $userId,
            'token' => $token,
            'language_code' => $languageCode,
            'continue_url' => $continueRegistrationUrl,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);

        if (!($insert->success ?? false)) {
            return (object) [
                'success' => false,
                'message' => 'Errore inserimento token verifica utente',
                'query' => $insert->query ?? '',
            ];
        }

        return (object) [
            'success' => true,
            'user_id' => $userId,
            'token_id' => (int) ($insert->insert_id ?? 0),
            'token' => $token,
            'continue_registration_url' => $continueRegistrationUrl,
            'language_code' => $languageCode,
            'expires_at' => $expiresAt,
            'user_email' => (string) ($userSql->row['email'] ?? ''),
        ];

    }

    /**
     * Conferma token verifica utente e marca utente come verificato.
     * In caso di errore valorizza sempre anche $ALERT.
     */
    function confirmUserVerificationToken(string $token, ?string $fallbackContinueUrl = null): object
    {

        global $mysqli;

        $token = trim($token);

        if ($token === '') {
            return userVerificationFailure('Token verifica utente mancante', 913);
        }

        $SQL = sqlSelect('consent_confirmation_tokens', [
            'token' => $token,
            'token_type' => userEmailVerificationTokenType()
        ], 1);

        if (!$SQL->exists || !is_array($SQL->row)) {
            return userVerificationFailure('Token verifica utente non trovato', 913);
        }

        $row = $SQL->row;
        $tokenId = (int) ($row['id'] ?? 0);
        $userId = (int) ($row['user_id'] ?? 0);
        $expiresAt = (string) ($row['expires_at'] ?? '');

        if (!empty($row['revoked_at'])) {
            return userVerificationFailure('Token verifica utente revocato', 913);
        }

        if (!empty($row['confirmed_at'])) {
            return userVerificationFailure('Token verifica utente già utilizzato', 913);
        }

        if ($expiresAt === '' || strtotime($expiresAt) < time()) {
            return userVerificationFailure('Token verifica utente scaduto', 914);
        }

        if ($userId <= 0 || $tokenId <= 0) {
            return userVerificationFailure('Token verifica utente non valido', 913);
        }

        $now = date('Y-m-d H:i:s');
        $continueUrl = (string) ($row['continue_url'] ?? '');
        if ($continueUrl === '') {
            $continueUrl = userEmailVerificationNormalizeContinueUrl($fallbackContinueUrl) ?? '';
        }

        $hasTransaction = ($mysqli instanceof \mysqli);

        if ($hasTransaction) {
            $mysqli->begin_transaction();
        }

        try {

            if ($hasTransaction) {

                $escapedNow = $mysqli->real_escape_string($now);
                $sql = "UPDATE `consent_confirmation_tokens` ";
                $sql .= "SET `confirmed_at` = '{$escapedNow}' ";
                $sql .= "WHERE `id` = {$tokenId} ";
                $sql .= "AND `token_type` = '".$mysqli->real_escape_string(userEmailVerificationTokenType())."' ";
                $sql .= "AND `confirmed_at` IS NULL AND `revoked_at` IS NULL";

                if (!$mysqli->query($sql) || $mysqli->affected_rows !== 1) {
                    throw new RuntimeException('Token verifica utente già utilizzato o revocato');
                }

            } else {

                $modify = sqlModify('consent_confirmation_tokens', [ 'confirmed_at' => $now ], 'id', $tokenId);
                if (!($modify->success ?? false)) {
                    throw new RuntimeException('Impossibile aggiornare token verifica utente');
                }

            }

            $mark = markUserEmailVerified($userId, $now);
            if (!($mark->success ?? false)) {
                throw new RuntimeException((string) ($mark->message ?? 'Impossibile verificare email utente'));
            }

            if ($hasTransaction) {
                $mysqli->commit();
            }

        } catch (Throwable $exception) {

            if ($hasTransaction) {
                $mysqli->rollback();
            }

            return userVerificationFailure('Errore conferma verifica utente: '.$exception->getMessage(), 900);

        }

        return (object) [
            'success' => true,
            'user_id' => $userId,
            'token_id' => $tokenId,
            'verified_at' => $now,
            'continue_registration_url' => $continueUrl,
        ];

    }

    /**
     * Costruisce subject/plain/html per email di verifica.
     */
    function buildUserEmailVerificationMessage(
        string $verificationUrl,
        ?string $expiresAt = null,
        ?string $continueRegistrationUrl = null
    ): array {

        $verificationUrl = trim($verificationUrl);
        $continueRegistrationUrl = userEmailVerificationNormalizeContinueUrl($continueRegistrationUrl);
        $expiresLine = '';

        if (!empty($expiresAt)) {
            $expiresLine = $expiresAt;
        }

        $subject = (string) __t('emails.email_verification.subject');
        $bodyText = (string) __t('emails.email_verification.text');
        $button = (string) __t('emails.email_verification.button');

        $safeVerificationUrl = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');
        $safeContinueUrl = htmlspecialchars((string) $continueRegistrationUrl, ENT_QUOTES, 'UTF-8');
        $safeButtonLabel = htmlspecialchars($button, ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safeBodyText = htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8');

        $text = $bodyText."\n\n";
        $text .= $button.": ".$verificationUrl."\n";

        if (!empty($continueRegistrationUrl)) {
            $text .= "\n".$continueRegistrationUrl."\n";
        }

        if ($expiresLine !== '') {
            $text .= "\n".$expiresLine."\n";
        }

        $html = "<p>{$safeBodyText}</p>";
        $html .= "<p><a href=\"{$safeVerificationUrl}\" target=\"_blank\" rel=\"noopener noreferrer\">{$safeButtonLabel}</a></p>";

        if (!empty($continueRegistrationUrl)) {
            $html .= "<p><a href=\"{$safeContinueUrl}\" target=\"_blank\" rel=\"noopener noreferrer\">{$safeContinueUrl}</a></p>";
        }

        if ($expiresLine !== '') {
            $safeExpiresLine = htmlspecialchars($expiresLine, ENT_QUOTES, 'UTF-8');
            $html .= "<p>{$safeExpiresLine}</p>";
        }

        return [
            'subject' => $subject,
            'text' => $text,
            'html' => "<div><h3>{$safeSubject}</h3>{$html}</div>",
        ];

    }

    /**
     * Normalizza lingua su 2 lettere prendendola dal LanguageContext.
     */
    function userEmailVerificationNormalizeLanguageCode(): string
    {

        $lang = 'it';

        if (class_exists('Wonder\\Localization\\LanguageContext')) {
            $lang = (string) Wonder\Localization\LanguageContext::getLang();
        }

        if ($lang === '') {
            $lang = 'it';
        }

        $lang = strtolower($lang);
        $lang = str_replace('_', '-', $lang);

        if (str_contains($lang, '-')) {
            $lang = explode('-', $lang)[0];
        }

        $lang = preg_replace('/[^a-z]/', '', $lang) ?? '';
        $lang = substr($lang, 0, 2);

        if ($lang === '') {
            $lang = 'it';
        }

        return $lang;

    }

    /**
     * Normalizza URL di prosecuzione registrazione.
     */
    function userEmailVerificationNormalizeContinueUrl(?string $continueUrl): ?string
    {

        if ($continueUrl === null) {
            return null;
        }

        $continueUrl = trim($continueUrl);

        if ($continueUrl === '') {
            return null;
        }

        $continueUrl = str_replace([ "\r", "\n" ], '', $continueUrl);

        return $continueUrl;

    }

    /**
     * Appende query params preservando query esistente.
     */
    function userEmailVerificationAppendQuery(string $url, array $query): string
    {

        $url = trim($url);

        if ($url === '' || empty($query)) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($query, '', '&', PHP_QUERY_RFC3986);

    }

    /**
     * Token random per verifica email.
     */
    function userEmailVerificationGenerateToken(): string
    {

        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(32));
        }

        return hash('sha256', uniqid((string) mt_rand(), true));

    }

    /**
     * Tipo token usato per verifica email utente.
     */
    function userEmailVerificationTokenType(): string
    {

        return 'user_email_verification';

    }

    /**
     * Revoca token non usati/non revocati dell'utente.
     */
    function userEmailVerificationRevokeOpenTokens(int $userId, string $revokedAt): void
    {

        $open = sqlSelect(
            'consent_confirmation_tokens',
            "`user_id` = '".(int) $userId."' AND `token_type` = '".userEmailVerificationTokenType()."' AND `confirmed_at` IS NULL AND `revoked_at` IS NULL"
        );

        if (!($open->exists ?? false) || !is_array($open->row)) {
            return;
        }

        foreach ($open->row as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                continue;
            }
            sqlModify('consent_confirmation_tokens', [ 'revoked_at' => $revokedAt ], 'id', (int) $row['id']);
        }

    }

    /**
     * Crea risposta errore e valorizza $ALERT.
     */
    function userVerificationFailure(string $message, int $alertCode = 900): object
    {

        global $ALERT;

        $ALERT = $alertCode;

        return (object) [
            'success' => false,
            'message' => $message,
            'alert' => $alertCode,
        ];

    }
