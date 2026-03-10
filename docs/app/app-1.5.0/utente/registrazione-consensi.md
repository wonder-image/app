---
icon: check
---

# Registrazione consensi

## Obiettivo

Registrare in modo auditabile e versionato:

- accettazione termini (`terms_accept`)
- presa visione privacy (`privacy_ack`)
- consenso marketing separato (`marketing_optin`)
- conferma marketing double opt-in (`marketing_optin_confirmed`)
- revoca marketing (`marketing_withdrawn`)

## Funzioni wrapper da usare

File:

- `1.5.0/function/consent/consent.php`

Funzioni:

- `registerUserConsents(...)`
- `confirmUserMarketingOptIn(...)`
- `withdrawUserMarketingConsent(...)`
- `getUserConsentsSnapshot(...)`

## Tabelle coinvolte

- `legal_documents`
- `consent_events`
- `user_consent_state`
- `marketing_optin_tokens`

Definizione:

- `1.5.0/build/table/consent.php`

## Registrazione base in signup

Checkbox in UI (separate):

- obbligatoria: termini
- obbligatoria: privacy (presa visione)
- opzionale: marketing

Esempio:

```php
$result = registerUserConsents(
    (int) $userId,
    [
        'accept_terms' => true,
        'ack_privacy' => true,
        'accept_marketing' => true,
        'terms_document_id' => 10,
        'privacy_document_id' => 11,
        'marketing_document_id' => 12
    ],
    [
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'locale' => 'it',
        'source' => 'web',
        'ui_surface' => 'signup',
        'evidence_json' => [
            'checkbox_name' => 'accept_marketing',
            'form_version' => 'v1',
            'request_id' => 'req_123'
        ]
    ]
);
```

Output utile:

- `$result['events']` (id eventi creati)
- `$result['double_opt_in']` se marketing accettato

## Conferma marketing (double opt-in)

```php
$confirm = confirmUserMarketingOptIn($token, [
    'locale' => 'de',
    'source' => 'web',
    'ui_surface' => 'email_link'
]);
```

## Revoca marketing

```php
$withdraw = withdrawUserMarketingConsent((int) $userId, [
    'locale' => 'it',
    'source' => 'web',
    'ui_surface' => 'profile_settings'
]);
```

## Lettura stato consensi

```php
$snapshot = getUserConsentsSnapshot((int) $userId, 100);
```

## Regole importanti

- `privacy_ack` non è consenso marketing e non è consenso generale.
- `terms_accept` è accettazione contrattuale.
- Marketing separato e facoltativo.
- Il marketing è attivo solo dopo `marketing_optin_confirmed`.
- Ogni variazione crea nuovo evento, storico mai sovrascritto.

## Vincolo email verificata per marketing

Nel service, il marketing richiede utente verificato:

- `user.email_verified = 1` oppure
- `user.email_verified_at` valorizzato

Se non verificato:

- errore: `Per accettare il marketing devi prima confermare la tua email.`
