# Sistema Consensi GDPR (solo PHP)

{% hint style="info" %}
Per la lettura in GitBook usa le pagine aggiornate in `app-1.5.0`:

- [Utente](app-1.5.0/utente/README.md)
- [Verifica email](app-1.5.0/utente/verifica-email.md)
- [Registrazione consensi](app-1.5.0/utente/registrazione-consensi.md)
{% endhint %}

Questa implementazione usa:

- builder tabelle Wonder esteso (`ENUM`, `UNIQUE` compositi, `PRIMARY KEY` composta)
- repository + service PHP in `Wonder\Consent`
- funzioni legacy in `1.5.0/function/consent/`
- nessun endpoint API HTTP dedicato

## Tabelle create

- `legal_documents`
- `consent_events`
- `user_consent_state`
- `consent_confirmation_tokens`

Definizioni in:

- `1.5.0/build/table/consent.php`

Seed iniziale documenti legali `it/de/en`:

- `1.5.0/build/row/legal_documents.php`

## Builder SQL: nuove capacità

Disponibili in:

- `class/Sql/CreateTable.php`
- `class/Sql/TableSchema.php`

Supporto aggiunto:

- `ENUM` tramite opzione `enum`
- `UNIQUE` singole e composte
- `PRIMARY KEY` composta
- opzione `__table` per controllare `auto_id` e `audit_columns`

## Service consensi

Classe principale:

- `Wonder\Consent\Service\ConsentService`

Metodi:

- `registerBaseConsents(...)`
- `registerConsentByDocumentId(...)`
- `getUserConsents(...)`

## Wrapper function

Funzioni disponibili:

- `registerUserConsents(...)`
- `getUserConsentsSnapshot(...)`

File:

- `1.5.0/function/consent/consent.php`

---

## Sezione User: verifica email

Questa sezione copre solo la verifica email utente lato backend.

### Tabelle coinvolte

- `user` (campi: `email_verified`, `email_verified_at`)
- `consent_confirmation_tokens` con `token_type=user_email_verification` (token one-time, scadenza, revoca, lingua, continue URL)

Definizione tabelle:

- `1.5.0/build/table/user.php`
- `1.5.0/build/table/consent.php`

### Funzioni disponibili

File:

- `1.5.0/function/user/email_verification.php`

Funzioni principali:

- `prepareUserEmailVerificationEmail(int $userId, string $verifyBaseUrl, ?string $continueRegistrationUrl = null, int $ttlHours = 24): object`
- `confirmUserVerificationToken(string $token, ?string $fallbackContinueUrl = null): object`
- `isUserEmailVerified($userId): bool`

Funzioni di supporto/admin:

- `generateUserVerificationToken(...)` (genera solo token, non il link)
- `markUserEmailVerified(...)` (uso tecnico/admin/test)
- `unmarkUserEmailVerified(...)` (uso tecnico/admin/test)

### Flusso semplice consigliato

1. L'utente inserisce email.
2. Tu hai/crei il `userId`.
3. Generi payload email:

```php
$payload = prepareUserEmailVerificationEmail(
    (int) $userId,
    'https://example.com/auth/verify-email',
    'https://example.com/signup/continue',
    24
);
```

4. Se `success=true`, invii email con `sendEmail()` usando:
- `$payload->user_email`
- `$payload->email_subject`
- `$payload->email_html` o `$payload->email_text`
- `$payload->verification_url` (già pronto, contiene `token` e `lang`)

5. Endpoint pagina/link di verifica (click utente):

```php
$result = confirmUserVerificationToken($_GET['token'] ?? '', '/signup/continue');

if ($result->success) {
    // Hai già lo user id verificato
    $userId = (int) $result->user_id;
    $user = infoUser($userId);
    $continue = $result->continue_registration_url ?: '/signup/continue';
    header("Location: {$continue}");
    exit;
}

// In caso errore la funzione valorizza anche $ALERT
```

### Alert gestiti da `confirmUserVerificationToken`

- `913`: token mancante/non valido/non trovato/revocato/già usato
- `914`: token scaduto
- `900`: errore generico backend

### Lingua email verifica

La lingua è sempre presa da:

- `Wonder\Localization\LanguageContext::getLang()`

Testi email in:

- `resources/lang/{it|de|en|es|fr}/emails.json`

Chiavi usate:

- `emails.email_verification.subject`
- `emails.email_verification.text`
- `emails.email_verification.button`

---

## Sezione Registrazione Consensi: privacy e terms

Questa sezione copre la registrazione dei consensi GDPR.

### Distinzione corretta dei consensi

- `terms_accept`: accettazione contrattuale dei termini
- `privacy_ack`: presa visione informativa privacy (non consenso generale)

### Funzioni wrapper da usare

File:

- `1.5.0/function/consent/consent.php`

Funzioni:

- `registerUserConsents(...)`
- `getUserConsentsSnapshot(...)`

### Registrazione consensi in signup

Esempio:

```php
$result = registerUserConsents(
    (int) $userId,
    [
        'accept_terms_conditions' => true,
        'accept_privacy_policy' => true,
        'terms_conditions_id' => 10,
        'privacy_policy_id' => 11
    ],
    [
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'locale' => 'it', // 2 lettere
        'source' => 'web',
        'ui_surface' => 'signup',
        'required_document_types' => [ 'terms', 'privacy_policy' ],
        'evidence_json' => [
            'checkbox_name' => 'accept_privacy_policy',
            'form_version' => 'v1',
            'request_id' => 'req_123'
        ]
    ]
);
```

Output utile:

- `$result['events']` con id eventi creati
- per doc custom, `consent_type` viene salvato come `doc_<doc_type>`

### Lettura stato corrente + storico sintetico

```php
$snapshot = getUserConsentsSnapshot((int) $userId, 100);
```
