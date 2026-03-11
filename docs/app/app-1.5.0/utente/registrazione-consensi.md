---
icon: check
---

# Registrazione consensi

## Obiettivo

Registrare in modo auditabile e versionato:

- accettazione termini (`terms_accept`)
- presa visione privacy (`privacy_ack`)

## Funzioni wrapper da usare

File:

- `1.5.0/function/consent/consent.php`

Funzioni:

- `registerUserConsents(...)`
- `getUserConsentsSnapshot(...)`

## Tabelle coinvolte

- `legal_documents`
- `consent_events`
- `user_consent_state`
- `consent_confirmation_tokens`

Definizione:

- `1.5.0/build/table/consent.php`

## Registrazione base in signup

Checkbox in UI (separate):

- obbligatoria: termini
- obbligatoria: privacy (presa visione)

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
        'locale' => 'it',
        'source' => 'web',
        'ui_surface' => 'signup',
        'required_document_types' => [ 'terms_conditions', 'privacy_policy' ],
        'evidence_json' => [
            'checkbox_name' => 'accept_privacy_policy',
            'form_version' => 'v1',
            'request_id' => 'req_123'
        ]
    ]
);
```

Output utile:

- `$result['events']` (id eventi creati)

Convenzione campi form usata dal service:

- checkbox: `accept_<doc_type>`
- hidden documento: `<doc_type>_id`

Esempio con helper:

- `inputAcceptDocument('terms_conditions')` -> `accept_terms_conditions` + `terms_conditions_id`
- `inputAcceptDocument('privacy_policy')` -> `accept_privacy_policy` + `privacy_policy_id`
Per i tipi documento custom, il service salva `consent_type` come `doc_<doc_type>`.

## Lettura stato consensi

```php
$snapshot = getUserConsentsSnapshot((int) $userId, 100);
```

## Regole importanti

- `privacy_ack` non è consenso generale al trattamento.
- `terms_accept` è accettazione contrattuale.
- Ogni variazione crea nuovo evento, storico mai sovrascritto.
