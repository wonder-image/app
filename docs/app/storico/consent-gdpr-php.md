# Sistema Consensi GDPR (solo PHP)

{% hint style="info" %}
Per la lettura in GitBook usa le pagine aggiornate in `app`:

- [Utente](../concetti/utenti/README.md)
- [Verifica email](../concetti/utenti/verifica-email.md)
- [Registrazione consensi](../concetti/utenti/consensi.md)
{% endhint %}

Questa implementazione usa:

- builder tabelle Wonder esteso (`ENUM`, `UNIQUE` compositi, `PRIMARY KEY` composta)
- repository + service PHP in `Wonder\Consent`
- funzioni legacy in `app/function/consent/`
- nessun endpoint API HTTP dedicato

## Tabelle create

- `legal_documents`
- `consent_events`
- `user_consent_state`
- `consent_confirmation_tokens`

Definizioni in:

- `app/build/table/consent.php`

Seed iniziale documenti legali `it/de/en`:

- `app/build/row/legal_documents.php`

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

- `registerBaseConsents(int $userId, array $input, array $context)` — utenti
  registrati: scrive `consent_events` + `user_consent_state`.
- `registerLeadConsents(string $email, array $input, array $context)` — lead
  anonimi: scrive **solo** `consent_events` (`user_id = NULL`,
  `subject_email = $email`).
- `registerConsentByDocumentId(...)` — singolo documento per uno user noto.
- `getUserConsents(...)` — snapshot stato corrente + storico per uno user.

Identificatori del subject nello schema `consent_events`:

- `user_id` (FK `user`, nullable) — signup/update user.
- `subject_email` (varchar 320, nullable) — form pubblici anonimi.
- almeno uno dei due **deve** essere valorizzato.

Tracciabilità record sorgente ↔ consenso:

- `subject_ref_type` (varchar 120, nullable) — nome tabella sorgente.
- `subject_ref_id` (int, nullable) — id del record sorgente.
- indice composto `idx_subject_ref(subject_ref_type, subject_ref_id)`.

## Wrapper function

Funzioni disponibili (`app/function/consent/consent.php`):

- `registerUserConsents(int $userId, array $input, array $ctx)`
- `registerLeadConsents(string $email, array $input, array $ctx)`
- `recordResourceConsents(array $values, array $ctx)` — hook generico
  invocato automaticamente dai Resource controller (Backend + API) dopo
  `afterStore()`; instrada a `registerUserConsents` o
  `registerLeadConsents` in base al contesto, logga senza throw se manca
  un subject.
- `consentsForRecord(string $type, int $id, int $limit = 100)` — lookup
  polimorfico "dato un record sorgente, mostrami i consensi raccolti",
  con join a `legal_documents` per ottenere versione + content_hash.
- `getUserConsentsSnapshot(int $userId, int $historyLimit)`

## Hook automatico nei Resource

I controller invocano `recordResourceConsents()` subito dopo
`afterStore()`:

- `class/Api/Support/ResourceApiController.php::store()` con
  `source = 'api'`
- `class/Backend/Support/ResourcePageController.php::store()` con
  `source = 'admin'`

Entrambi passano `subject_ref_type = static::modelTable()` e
`subject_ref_id = $result->insert_id`, così ogni evento in
`consent_events` linka esplicitamente al record che l'ha generato.

Quindi qualunque `Resource` che dichiari un campo
`FormField::key('accept_<doc>')->acceptDocument('<doc>')` registra il
consenso senza scrivere una riga di codice nei suoi hook.

---

## Sezione User: verifica email

Questa sezione copre solo la verifica email utente lato backend.

### Tabelle coinvolte

- `user` (campi: `email_verified`, `email_verified_at`)
- `consent_confirmation_tokens` con `token_type=user_email_verification` (token one-time, scadenza, revoca, lingua, continue URL)

Definizione tabelle:

- `app/build/table/user.php`
- `app/build/table/consent.php`

### Funzioni disponibili

File:

- `app/function/user/email_verification.php`

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

- `918`: token mancante/non valido/non trovato/revocato/già usato
- `919`: token scaduto
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

- `app/function/consent/consent.php`

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
