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

- `app/function/consent/consent.php`

Funzioni:

- `registerUserConsents(...)` — utenti registrati (signup, update profilo)
- `registerLeadConsents(...)` — lead anonimi identificati solo dall'email (form contatto, newsletter, ...)
- `recordResourceConsents(...)` — hook generico chiamato automaticamente dai Resource controller dopo lo store; instrada al pipeline giusto (user o lead) in base al contesto
- `consentsForRecord(...)` — lookup polimorfico "dato un record sorgente, mostrami i consensi raccolti"
- `getUserConsentsSnapshot(...)` — stato corrente + storico per uno user

## Tabelle coinvolte

- `legal_documents`
- `consent_events` — registro append-only di ogni evento accept/reject/withdraw
- `user_consent_state` — stato corrente per gli utenti registrati (composite PK `user_id` + `consent_type`); **non** popolata per i lead
- `consent_confirmation_tokens`

Definizione (Model-driven, generato da `forge update --local`):

- `class/App/Models/Consent/ConsentEvent.php`
- `class/App/Models/Consent/UserConsentState.php`
- `class/App/Models/Consent/LegalDocument.php`

### Schema `consent_events` — identificatori e traccia

Ogni evento può essere legato al "subject" in due modi mutuamente esclusivi:

| Colonna | Tipo | Significato |
|---|---|---|
| `user_id` | `int` nullable, FK `user(id)` | Subject = utente registrato (signup, update profilo) |
| `subject_email` | `varchar(320)` nullable | Subject = lead anonimo identificato dall'email (form contatto, newsletter) |

Almeno uno dei due deve essere valorizzato — il `Repository::create()` rifiuta payload privi di entrambi.

Inoltre due colonne polimorfiche per la **tracciabilità record sorgente ↔ consenso**:

| Colonna | Tipo | Significato |
|---|---|---|
| `subject_ref_type` | `varchar(120)` nullable | Nome tabella sorgente (es. `requests`, `users`) |
| `subject_ref_id` | `int` nullable | Id del record sorgente |

Indicizzate insieme da `idx_subject_ref(subject_ref_type, subject_ref_id)` per lookup veloci nelle due direzioni.

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

### Helper DSL (FormField + acceptDocument)

Il vecchio helper procedurale `inputAcceptDocument()` resta, ma il DSL
fluent va preferito perché coerente con tutto il resto della Resource:

```php
use Wonder\App\ResourceSchema\FormField;

FormField::key('accept_privacy_policy')->acceptDocument('privacy_policy')->required()
FormField::key('accept_terms_conditions')->acceptDocument('terms_conditions')->required()
```

Genera lo stesso HTML del legacy: checkbox `accept_<doc_type>` + hidden
`<doc_type>_id` con id e label HTML del documento attivo nella lingua
corrente, risolto dal `FormFieldElementFactory` al render. La
`->required()` aggiunge anche l'`*` alla label.

> `acceptDocument()` resta sul `FormField` legacy finché il refactor
> "Inputs/*" (vedi `elementi/form-system.md`) non estrae anche
> `InputAcceptDocument` come classe dedicata. Quando arriverà
> (`Wonder\App\ResourceSchema\Inputs\InputAcceptDocument`), la firma
> sarà la stessa: `->document($type)`.

Per i tipi documento custom, il service salva `consent_type` come `doc_<doc_type>`.

## Registrazione automatica via Resource controller

Quando il form viene submittato a una `Resource` (sia API che backend
admin), i controller chiamano automaticamente
`recordResourceConsents()` subito dopo `afterStore()`. Il hook:

1. cerca chiavi `accept_*` in `$values` (merge di `$_POST` + `$values`
   preparati, così include sia gli hidden `<doc_type>_id` sia i campi
   whitelisted);
2. se è presente uno user loggato (`$_SESSION['user_id'] > 0`) →
   `registerUserConsents()` (popola sia `consent_events` sia
   `user_consent_state`);
3. altrimenti se è presente `$values['email']` valida →
   `registerLeadConsents()` (popola solo `consent_events` con
   `user_id = NULL` e `subject_email = email`);
4. altrimenti logga l'evento via `__log()` senza throw — il submit non
   viene mai abbattuto da una registrazione consenso fallita.

I controller passano anche il **link al record sorgente**:

```php
recordResourceConsents(
    array_merge($_POST, $values),
    [
        'source' => 'api',                             // o 'admin'
        'ui_surface' => $resourceClass::slug().'/store',
        'subject_ref_type' => $resourceClass::modelTable(),   // es. 'requests'
        'subject_ref_id' => $result->insert_id,
    ]
);
```

Quindi ogni evento in `consent_events` linka esplicitamente al record che
l'ha generato — senza match per email + timestamp.

## Lead consent (form pubblici anonimi)

Per i form pubblici (contatto, newsletter, lead magnet) lo subject del
consenso non è uno user ma una email. Il flusso:

```
POST /api/requests/store
    name=Andrea, email=andrea@example.com,
    accept_privacy_policy=true, privacy_policy_id=42, ...
        │
        ▼
ResourceApiController::store()
    INSERT INTO requests (...)
    afterStore(...)
    recordResourceConsents([...], [
        'source' => 'api',
        'subject_ref_type' => 'requests',
        'subject_ref_id' => $newRequestId,
    ])
        │
        ▼  (helper instrada)
registerLeadConsents('andrea@example.com', $payload, $ctx)
    INSERT INTO consent_events
        user_id = NULL
        subject_email = 'andrea@example.com'
        subject_ref_type = 'requests'
        subject_ref_id = 42
        consent_type = 'privacy_ack'
        action = 'accept'
        legal_document_id = 42
        ...
```

I lead non hanno una riga in `user_consent_state` perché non hanno uno
"stato persistente" da tracciare — ogni interazione è un evento
standalone.

Chiamata diretta (fuori dal flusso Resource):

```php
$result = registerLeadConsents(
    'lead@example.com',
    [
        'accept_privacy_policy' => true,
        'privacy_policy_id' => 11,
    ],
    [
        'source' => 'web',
        'ui_surface' => 'newsletter-popup',
        'subject_ref_type' => 'newsletter_subscriptions',
        'subject_ref_id' => $subscriptionId,
    ]
);
```

## Lettura stato consensi

Per uno **user registrato**:

```php
$snapshot = getUserConsentsSnapshot((int) $userId, 100);
```

Per qualsiasi record sorgente (user, request, lead, ...):

```php
$events = consentsForRecord('requests', $requestId);
// SELECT ce.*, ld.doc_type, ld.version, ld.content_hash
// FROM consent_events ce
// LEFT JOIN legal_documents ld ON ld.id = ce.legal_document_id
// WHERE ce.subject_ref_type = 'requests'
//   AND ce.subject_ref_id = $requestId
// ORDER BY ce.occurred_at DESC
```

Il join al `legal_documents` restituisce **quale versione** del documento
è stata accettata, con il suo `content_hash`. È quella la prova GDPR
vera da archiviare/esporre — non basta sapere "ha accettato": serve
sapere "ha accettato la versione X del documento Y, il cui contenuto
all'epoca era hash Z".

## URL backend del consenso e del record sorgente

Ogni riga di `consent_events` letta via `Wonder\App\Models\Consent\ConsentEvent`
(quindi via `find()`, `findById()`, `all()`, e tramite il pattern
`Model::decorate()` — vedi
[manuale Model](../../backend/resource-manuale/quick-start.md))
include automaticamente due URL backend computati:

| Chiave | Cosa è | Quando è `null` |
|---|---|---|
| `backend_url` | Dettaglio del **consent_event** nel backend (es. `/admin/log/consent/42/`) | id mancante, Resource non registrata |
| `source_backend_url` | Dettaglio del **record sorgente** che ha generato il consenso (es. `/admin/requests/123/`) | `subject_ref_*` non valorizzati, Resource sorgente non registrata |

```php
$events = consentsForRecord('requests', $requestId);

foreach ($events as $event) {
    echo '<a href="'.$event['backend_url'].'">Apri evento</a>';
    if ($event['source_backend_url'] !== null) {
        echo '<a href="'.$event['source_backend_url'].'">Apri richiesta</a>';
    }
}
```

I due URL sono calcolati da:

- `ConsentEvent::backendUrl($row)` — link al consenso stesso (risolve la
  Resource tramite `ResourceRegistry::resolveByTable('consent_events')`)
- `ConsentEvent::sourceBackendUrl($row)` — link al record sorgente
  (risolve via `ResourceRegistry::resolveByTable($row['subject_ref_type'])`)

Sono accessibili anche standalone, senza passare da `decorate()`, se
hai una riga grezza in mano (es. da `ConsentEventRepository`).

## Regole importanti

- `privacy_ack` non è consenso generale al trattamento.
- `terms_accept` è accettazione contrattuale.
- Ogni variazione crea nuovo evento, storico mai sovrascritto.
- `consent_events.user_id` e `subject_email` sono **mutuamente
  esclusivi**: o uno o l'altro, ma almeno uno dei due valorizzato.
- `subject_ref_type` + `subject_ref_id` sono opzionali ma fortemente
  consigliati per ricostruire la catena d'evidenza.

## File chiave (mappa)

| Concetto | File |
|---|---|
| Schema tabella | `class/App/Models/Consent/ConsentEvent.php` |
| Repository (INSERT/SELECT) | `class/Consent/Repository/ConsentEventRepository.php` |
| Service (pipeline transazionale) | `class/Consent/Service/ConsentService.php` |
| Wrapper procedurali | `app/function/consent/consent.php` |
| Hook auto API store | `class/Api/Support/ResourceApiController.php::store()` |
| Hook auto backend store | `class/Backend/Support/ResourcePageController.php::store()` |
| DSL acceptDocument | `class/App/ResourceSchema/FormField.php` |
| Render checkbox | `class/Themes/Wonder/Form/Components/InputAcceptDocument.php` |
| Hook decorate righe ConsentEvent | `class/App/Models/Consent/ConsentEvent.php::decorate()` |
| Base Model + hook decorate | `class/App/Model.php::decorate()` / `::decorateRows()` |
