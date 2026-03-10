---
icon: envelope
---

# Verifica email

## Obiettivo

Verificare l'email utente con token one-time prima di consentire il marketing e il completamento della registrazione.

## Tabelle coinvolte

- `user` (`email_verified`, `email_verified_at`)
- `user_verification_tokens` (`token`, `expires_at`, `used_at`, `revoked_at`, `lang`, `continue_url`)

Definizione:

- `1.5.0/build/table/user.php`

## Funzioni disponibili

File:

- `1.5.0/function/user/email_verification.php`

Principali:

- `prepareUserEmailVerificationEmail(int $userId, string $verifyBaseUrl, ?string $continueRegistrationUrl = null, int $ttlHours = 24): object`
- `confirmUserVerificationToken(string $token, ?string $fallbackContinueUrl = null): object`
- `isUserEmailVerified($userId): bool`

Supporto/admin:

- `generateUserVerificationToken(...)` (genera solo token)
- `markUserEmailVerified(...)`
- `unmarkUserEmailVerified(...)`

## Flusso consigliato

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
- `$payload->verification_url`

5. L'utente clicca il link e sul tuo endpoint richiami:

```php
$result = confirmUserVerificationToken($_GET['token'] ?? '', '/signup/continue');

if ($result->success) {
    $userId = (int) $result->user_id;
    $user = infoUser($userId);

    $continue = $result->continue_registration_url ?: '/signup/continue';
    header("Location: {$continue}");
    exit;
}

// In caso errore: $ALERT già valorizzato dalla funzione
```

## Alert gestiti

- `913`: token mancante/non valido/non trovato/revocato/già usato
- `914`: token scaduto
- `900`: errore generico backend

## Lingua email

La lingua è sempre letta da:

- `Wonder\Localization\LanguageContext::getLang()`

Traduzioni in:

- `resources/lang/{it|de|en|es|fr}/emails.json`

Chiavi usate:

- `emails.email_verification.subject`
- `emails.email_verification.text`
- `emails.email_verification.button`

## Nota importante

Sul link pubblico **non** chiamare direttamente `markUserEmailVerified()`.
Usa sempre `confirmUserVerificationToken($token)` perché valida scadenza, stato token e atomicità della conferma.
