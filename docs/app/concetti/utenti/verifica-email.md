---
icon: envelope
---

# Verifica email

## Obiettivo

Verificare l'email utente con token one-time prima di consentire il marketing e il completamento della registrazione.

## Tabelle coinvolte

- `user` (`email_verified`, `email_verified_at`)
- `consent_confirmation_tokens` (`token_type=user_email_verification`, `token_hash`, `token`, `expires_at`, `confirmed_at`, `revoked_at`, `language_code`, `continue_url`)

Definizione:

- `class/App/Models/User/User.php` (tabella `user`)
- `class/App/Models/Consent/ConsentConfirmationToken.php` (tabella `consent_confirmation_tokens`)

Il token del link non si salva: la riga contiene solo `token_hash`
(sha256, 64 caratteri, unico) e il chiamante riceve il valore in chiaro una
volta sola, in `generateUserVerificationToken()->token`. La colonna `token`
resta, nullable, per le righe emesse prima: `confirmUserVerificationToken()`
cerca per `token_hash` e, se non trova nulla, per `token` in chiaro. **Ripiego
per un solo rilascio, rimosso nella beta successiva.**

## Funzioni disponibili

File:

- `app/function/user/email_verification.php`

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

- `918`: token mancante/non valido/non trovato/revocato/già usato
- `919`: token scaduto
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
La conferma è un `UPDATE … WHERE id = ? AND confirmed_at IS NULL AND revoked_at IS NULL`
con controllo delle righe modificate (senza `mysqli` rilegge la riga subito
prima di scrivere): due clic sullo stesso link verificano l'utente una volta
sola, il secondo riceve `918`.
