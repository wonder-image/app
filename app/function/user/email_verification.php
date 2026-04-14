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
            return userVerificationFailure(900);
        }

        $verifiedAt = $verifiedAt ?: date('Y-m-d H:i:s');

        $update = sqlModify('user', [
            'email_verified' => 1,
            'email_verified_at' => $verifiedAt,
        ], 'id', $userId);

        if (!($update->success ?? false)) {
            return userVerificationFailure(900, [
                'query' => $update->query ?? '',
            ]);
        }

        return (object) [
            'success' => true,
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
            return userVerificationFailure(900);
        }

        $update = sqlModify('user', [
            'email_verified' => 0,
            'email_verified_at' => null,
        ], 'id', $userId);

        if (!($update->success ?? false)) {
            return userVerificationFailure(900, [
                'query' => $update->query ?? '',
            ]);
        }

        return (object) [
            'success' => true,
            'query' => $update->query ?? '',
        ];

    }

    /**
     * Genera payload completo (token + link + testo email) per verifica email.
     */
    function prepareUserEmailVerificationEmail(
        int $userId,
        string $verifyBaseUrl,
        int $ttlHours = 24,
        ?array $metadata = null
    ): object {

        $verifyBaseUrl = trim($verifyBaseUrl);
        if ($verifyBaseUrl === '') {
            return userVerificationFailure(900);
        }

        $tokenPayload = generateUserVerificationToken($userId, $ttlHours, $metadata);

        if (($tokenPayload->already_verified ?? false) === true) {
            return $tokenPayload;
        }

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
            (string) $tokenPayload->expires_at
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
        int $ttlHours = 24,
        ?array $metadata = null
    ): object {

        global $ALERT;

        $userId = (int) $userId;
        $metadata = userEmailVerificationNormalizeMetadata($metadata);
        $flow = (string) $metadata['flow'];
        $requestedFromUrl = (string) ($metadata['requested_from_url'] ?? '');

        $fail = function (int $alertCode, array $extra = []) use (&$ALERT, $flow, $requestedFromUrl): object {
            $ALERT = $alertCode;

            return (object) array_merge([
                'success' => false,
                'alert_code' => $alertCode,
                'flow' => $flow,
                'requested_from_url' => $requestedFromUrl,
            ], $extra);
        };

        if ($userId <= 0) {
            return $fail(900);
        }

        $ttlHours = max(1, min(720, $ttlHours > 0 ? $ttlHours : 24));

        $userSql = sqlSelect('user', [ 'id' => $userId ], 1);
        if (!$userSql->exists) {
            return $fail(900);
        }

        if (isUserEmailVerified($userId)) {
            return (object) [
                'success' => true,
                'alert_code' => null,
                'already_verified' => true,
                'user_id' => $userId,
                'user_email' => (string) ($userSql->row['email'] ?? ''),
                'flow' => $flow,
                'requested_from_url' => $requestedFromUrl,
            ];
        }

        $languageCode = userEmailVerificationNormalizeLanguageCode();
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($metadataJson)) {
            return $fail(900);
        }

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
            'metadata_json' => $metadataJson,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);

        if (!($insert->success ?? false)) {
            return $fail(900, [
                'query' => $insert->query ?? '',
            ]);
        }

        return (object) [
            'success' => true,
            'alert_code' => null,
            'user_id' => $userId,
            'token_id' => (int) ($insert->insert_id ?? 0),
            'token' => $token,
            'metadata' => $metadata,
            'language_code' => $languageCode,
            'expires_at' => $expiresAt,
            'user_email' => (string) ($userSql->row['email'] ?? ''),
            'flow' => $flow,
            'requested_from_url' => $requestedFromUrl,
        ];

    }

    /**
     * Conferma token verifica utente e marca utente come verificato.
     * In caso di errore valorizza sempre anche $ALERT.
     */
    function confirmUserVerificationToken(string $token): object
    {

        global $mysqli;
        global $ALERT;

        $token = trim($token);
        $tokenType = trim((string) userEmailVerificationTokenType());
        $flow = 'default';
        $requestedFromUrl = '';

        $fail = function (int $alertCode, array $extra = []) use (&$ALERT, &$flow, &$requestedFromUrl): object {
            $ALERT = $alertCode;

            return (object) array_merge([
                'success' => false,
                'alert_code' => $alertCode,
                'flow' => $flow,
                'requested_from_url' => $requestedFromUrl,
            ], $extra);
        };

        if ($token === '') {
            return $fail(918);
        }

        if ($tokenType === '') {
            return $fail(900);
        }

        $SQL = sqlSelect('consent_confirmation_tokens', [
            'token' => $token,
            'token_type' => $tokenType
        ], 1);

        if (!$SQL->exists || !is_array($SQL->row)) {
            return $fail(918);
        }

        $row = $SQL->row;
        $metadata = userEmailVerificationDecodeMetadata($row['metadata_json'] ?? null);
        $flow = (string) $metadata['flow'];
        $requestedFromUrl = (string) ($metadata['requested_from_url'] ?? '');
        $tokenId = (int) ($row['id'] ?? 0);
        $userId = (int) ($row['user_id'] ?? 0);
        $expiresAt = (string) ($row['expires_at'] ?? '');

        if (!empty($row['revoked_at'])) {
            return $fail(918);
        }

        if (!empty($row['confirmed_at'])) {
            return $fail(918);
        }

        if ($expiresAt === '' || strtotime($expiresAt) < time()) {
            return $fail(919);
        }

        if ($userId <= 0 || $tokenId <= 0) {
            return $fail(918);
        }

        $now = date('Y-m-d H:i:s');
        $redirectBase64 = (string) ($metadata['redirect_base64'] ?? '');
        $redirectUrl = '';
        if ($redirectBase64 !== '') {
            $decodedRedirect = base64_decode($redirectBase64, true);
            if (is_string($decodedRedirect)) {
                $redirectUrl = trim(str_replace([ "\r", "\n" ], '', $decodedRedirect));
            }
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
                $sql .= "AND `token_type` = '".$mysqli->real_escape_string($tokenType)."' ";
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

            return $fail(900);

        }

        return (object) [
            'success' => true,
            'alert_code' => null,
            'user_id' => $userId,
            'token_id' => $tokenId,
            'verified_at' => $now,
            'flow' => $flow,
            'redirect_base64' => $redirectBase64,
            'redirect_url' => $redirectUrl,
            'requested_from_url' => $requestedFromUrl,
            'metadata' => $metadata,
        ];

    }

    /**
     * Costruisce subject/plain/html per email di verifica.
     */
    function buildUserEmailVerificationMessage(
        string $verificationUrl,
        ?string $expiresAt = null
    ): array {

        $verificationUrl = trim($verificationUrl);
        $expiresLine = '';

        if (!empty($expiresAt)) {
            $expiresLine = $expiresAt;
        }

        $subject = (string) __t('emails.email_verification.subject');
        $bodyText = (string) __t('emails.email_verification.text');
        $button = (string) __t('emails.email_verification.button');

        $safeVerificationUrl = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');
        $safeButtonLabel = htmlspecialchars($button, ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safeBodyText = htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8');

        $text = $bodyText."\n\n";
        $text .= $button.": ".$verificationUrl."\n";

        if ($expiresLine !== '') {
            $text .= "\n".$expiresLine."\n";
        }

        $html = "<p>{$safeBodyText}</p>";
        $html .= "<p><a href=\"{$safeVerificationUrl}\" target=\"_blank\" rel=\"noopener noreferrer\">{$safeButtonLabel}</a></p>";

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

        $lang = (string) __l();
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
     * Normalizza il flow applicativo (es: login, signup).
     */
    function userEmailVerificationNormalizeFlow(string $flow): string
    {

        $flow = strtolower(trim($flow));
        $flow = preg_replace('/[^a-z0-9_-]/', '', $flow) ?? '';

        return $flow !== '' ? $flow : 'default';

    }

    /**
     * Normalizza il redirect in base64.
     */
    function userEmailVerificationNormalizeRedirectBase64(?string $redirectBase64): string
    {

        if ($redirectBase64 === null) {
            return '';
        }

        $redirectBase64 = trim($redirectBase64);

        if ($redirectBase64 === '') {
            return '';
        }

        return str_replace([ "\r", "\n" ], '', $redirectBase64);

    }

    /**
     * Normalizza URL sorgente della richiesta invio mail.
     */
    function userEmailVerificationNormalizeRequestedFromUrl(?string $requestedFromUrl): string
    {

        if ($requestedFromUrl === null) {
            return '';
        }

        $requestedFromUrl = trim($requestedFromUrl);

        if ($requestedFromUrl === '') {
            return '';
        }

        return str_replace([ "\r", "\n" ], '', $requestedFromUrl);

    }

    /**
     * Normalizza metadata da salvare nel token.
     *
     * @return array<string, mixed>
     */
    function userEmailVerificationNormalizeMetadata(?array $metadata): array
    {

        $metadata = is_array($metadata) ? $metadata : [];
        $flow = userEmailVerificationNormalizeFlow((string) ($metadata['flow'] ?? 'default'));
        $redirectBase64 = userEmailVerificationNormalizeRedirectBase64((string) ($metadata['redirect_base64'] ?? ''));
        $requestedFromUrl = userEmailVerificationNormalizeRequestedFromUrl((string) ($metadata['requested_from_url'] ?? ''));

        $normalized = [ 'flow' => $flow ];

        if ($redirectBase64 !== '') {
            $normalized['redirect_base64'] = $redirectBase64;
        }
        if ($requestedFromUrl !== '') {
            $normalized['requested_from_url'] = $requestedFromUrl;
        }

        return $normalized;

    }

    /**
     * Decodifica metadata token da JSON.
     *
     * @return array<string, mixed>
     */
    function userEmailVerificationDecodeMetadata(?string $rawMetadata): array
    {

        if ($rawMetadata === null) {
            return [ 'flow' => 'default' ];
        }

        $rawMetadata = trim($rawMetadata);

        if ($rawMetadata === '') {
            return [ 'flow' => 'default' ];
        }

        $decoded = json_decode($rawMetadata, true);

        if (!is_array($decoded)) {
            return [ 'flow' => 'default' ];
        }

        return userEmailVerificationNormalizeMetadata($decoded);

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

        return bin2hex(random_bytes(32));

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
     * Crea risposta errore guidata dal codice alert e valorizza $ALERT.
     */
    function userVerificationFailure(int $alertCode = 900, array $extra = []): object
    {

        global $ALERT;

        $ALERT = $alertCode;

        $response = [
            'success' => false,
            'message' => (string) __t("notifications.{$alertCode}.text"),
            'alert' => $alertCode,
            'alert_code' => $alertCode,
        ];

        foreach ($extra as $key => $value) {
            if (!is_string($key) || $key === '' || $key === 'success' || $key === 'alert' || $key === 'alert_code') {
                continue;
            }

            $response[$key] = $value;
        }

        return (object) $response;

    }
