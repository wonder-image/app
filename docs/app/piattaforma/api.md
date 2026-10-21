---
icon: circle-nodes
---

# Api

Questa è la struttura reale delle API oggi.

Le API route stanno in:

- `app/config/routes/route.api.php`
- `custom/config/routes/route.api.php`

Gli handler API stanno in:

- `app/http/api/...`
- `custom/http/api/...`

I vecchi file sotto `app/api/*` sono stati rimossi dal package.
La logica API vive in `app/http/api/*` e viene esposta solo tramite route dichiarate in `route.api.php`.

Il boilerplate corretto non è più quello vecchio con `try/catch` scritto a mano in ogni file.
Adesso il pattern da usare è questo.

## API backend di sessione

Le route sotto `/api/backend/` (gruppo `backend.` di `route.api.php`) usano la
sessione del backend, non un Bearer: per questo il gruppo dichiara `->csrf()` e
ogni POST senza token CSRF valido (`_csrf` o header `X-WI-CSRF`) riceve `403`
`{"success": false, "status": 403, "response": "Token CSRF non valido."}` senza
che l'handler parta. Fa eccezione `/api/backend/alert/`, che usa `Handler::run`
con Bearer. Un endpoint di sito aggiunto a questo gruppo eredita la verifica;
uno di sessione aggiunto altrove deve dichiarare `->csrf()`. Dettagli in
[Token CSRF](../concetti/form/csrf.md#route-con-csrf).

## Endpoint base

```php
<?php

use Wonder\Api\{ Endpoint, Handler, Response };

Handler::run('/api/app/ping/', 'POST', 'api_internal_user', function (Endpoint $CALL) {
    return Response::json([
        'success' => true,
        'status' => 200,
        'response' => 'pong',
    ]);
});
```

## Esempio reale update

File:

- `app/http/api/app/update.php`

Schema reale:

```php
<?php

use Wonder\Api\{ Endpoint, Handler, Response };

Handler::run('/api/app/update/', 'POST', [ 'api_internal_user', 'api_public_access' ], function (Endpoint $CALL) {

    $PARAMETERS = is_array($CALL->parameters) ? $CALL->parameters : [];

    $RUNNER = new Wonder\App\UpdateRunner();
    $RESULT = $RUNNER->execute(Wonder\App\UpdateRunner::apiOptions($PARAMETERS));

    return Response::raw(
        $RUNNER->jsonPayload($RESULT),
        $RESULT->success ? 200 : 500
    );
}, allowPageToken: false);
```

`UpdateRunner::apiOptions()` legge `release_id`, `source` (`github`|`backend`, default `github`) e `backup`: default `true`; `false`, `0`, `"false"`, `"no"`, `"off"` saltano il backup del database prima delle migrazioni, come `php forge update --no-backup`. Lo usa anche il percorso con token di deploy dello stesso file.

`allowPageToken: false` è per gli endpoint chiamati solo da server (deploy, cron, trigger esterni): rifiutano il token di pagina (vedi sotto).

## Classi da conoscere

- `class/Api/Endpoint.php`
- `class/Api/EndpointException.php`
- `class/Api/Handler.php`
- `class/Api/Response.php`
- `class/Api/Token/BearerVerifier.php`: verifica del bearer, riusabile fuori da `Endpoint`
- `class/Api/Token/ApiTokens.php`: emissione dei token (`issue()`, `pageToken()`, `fingerprint()`)
- `class/Api/Token/TokenConfig.php`: durate lette dal `.env`
- `class/Api/Contracts/ApiUserProvider.php`: da dove arriva l'utente (default `infoUser()`)

## Token

### Modello

Un token API è un JWT HS256 firmato con `APP_KEY`, salvato in chiaro in `api_users.token` (serve a `Credentials::appToken()` e ai trigger server). La firma da sola non basta: `BearerVerifier` controlla nell'ordine

1. firma e scadenza (`exp`): scaduto → `401 Token scaduto`, firma o formato errati → `401 Token non valido` (prima erano un `500`);
2. utente del `sub` esistente e attivo, con una delle authority dell'endpoint (`403` altrimenti);
3. bearer **uguale** a `api_users.token` dell'utente, confrontato con `hash_equals()`;
4. riga del token attiva, IP e domini ammessi.

Ogni token ha un `jti` casuale (`bin2hex(random_bytes(16))`, prima era `uniqid()`).

Il punto 3 non c'era: prima bastava un JWT firmato con il `sub` giusto, quindi rigenerare o revocare un token non aveva effetto.

### Rotazione e revoca

```php
$token = regenerateApiToken($userId);        // nuovo token, il vecchio dà subito 401
$token = regenerateApiToken($userId, 86400); // con scadenza a 24 ore
```

Revocare significa svuotare `api_users.token` o mettere la riga `active = false`. Rigenerare il token di `@system` (id 1 di `api_users`) invalida anche tutti i token di pagina già emessi; i trigger esterni (cron, deploy) vanno aggiornati col nuovo `Credentials::appToken()`.

### Scadenza

| Chiave `.env` | Default | Significato |
| --- | --- | --- |
| `API_TOKEN_TTL` | `0` | secondi di vita dei token emessi da `apiUser()` / `regenerateApiToken()`; `0` = senza `exp` |
| `API_PAGE_TOKEN_TTL` | `3600` (1 ora) | vita del token di pagina, da 300 a 604800 |
| `API_TOKEN_REQUIRE_EXP` | spento | `true` rifiuta i token senza `exp` |

Il default resta `0` perché gli utenti API dei moduli (per esempio `@immobili`, il cui token è salvato nell'URL di push di Gestim) non sanno rinnovarsi. Il token di `@system` resta sempre senza scadenza (`_api_token_ttl => 0` in `build/row/user.php`). `apiUser()` accetta `_api_token_ttl` per scegliere la durata di un singolo utente.

Percorso di migrazione verso token con scadenza:

1. imposta `API_TOKEN_TTL` (per esempio `31536000`, un anno);
2. rigenera i token degli utenti API con `regenerateApiToken()` e consegna i nuovi;
3. quando non restano token senza `exp` (tranne `@system`, che va escluso o rigenerato con TTL e aggiornato nei trigger), attiva `API_TOKEN_REQUIRE_EXP=true`.

### Token di pagina (`API_TOKEN`)

Le pagine frontend e backend stampano `const API_TOKEN`, che la lib manda come `Authorization: Bearer` da `API_CLIENT` / `API_CLIENT_APP`. Prima era il token di `@system`, cioè un bearer riutilizzabile senza scadenza consegnato a ogni visitatore. Ora è `ApiTokens::pageToken()`: un JWT di `@system` con `typ: page`, `exp` obbligatoria (`API_PAGE_TOKEN_TTL`, 1 ora), legato al token di sistema corrente (`tid` + `tkh`, un'impronta parziale), senza contenerlo, e alla sessione (`sid`).

- Gli endpoint con `api_internal_user` lo accettano come prima: le chiamate della lib non cambiano.
- Gli endpoint con `allowPageToken: false` lo rifiutano con `401` (`/api/app/update/`, `/api/task/scheduler/`, `/api/task/sitemap/`).
- Le route Resource (`api.resource.*`) lo rifiutano con `401` salvo `apiSchema()->allowPageToken()`: vedi [Route e API](../concetti/risorse/route-e-api.md#chi-puo-chiamare-le-route-api).
- **Legato alla sessione.** Con una sessione PHP attiva (sempre nelle pagine, anche anonime) il token porta `sid`, un HMAC dell'id di sessione con `APP_KEY`: vale solo insieme al cookie di quella sessione, altrimenti `401 Token di pagina non valido per questa sessione`. Copiato fuori dal browser, o dopo `session_regenerate_id()` (login, logout), non vale più.
- **Rinnovo**: `POST /api/token/page/` (rotta `token.page`, area `api`) restituisce `{ "success": true, "token": "…", "expires_in": 3600 }`, un token nuovo per la stessa sessione. Non ha guard ma risponde solo a richieste same-origin (`Wonder\Http\SameOrigin`: `Sec-Fetch-Site: same-origin`, oppure `Origin` con lo stesso host e porta), con una sessione attiva e con il token CSRF di sessione (header `X-WI-CSRF` dal meta `wi-csrf`, oppure `_csrf` nel body: `Wonder\Http\Csrf::verify()`); altrimenti `403`, `405` se non è POST. La risposta ha `Cache-Control: no-store`. La lib non lo usa ancora: una scheda rimasta aperta oltre la durata riceve `401 Token scaduto` e va ricaricata.
- I token senza `sid`, emessi prima di questa versione, valgono fino alla loro `exp` (al massimo il vecchio `API_PAGE_TOKEN_TTL`), poi `401`. Il ramo di compatibilità per i token senza `sid` sarà rimosso nella beta successiva.
- `$CALL->isPageToken()` dice all'endpoint se la chiamata arriva da una pagina.

Il token di pagina dà comunque accesso, per la sua durata, agli endpoint `api_internal_user`: la chiusura completa richiede che la lib chiami quelli con la sessione e il token CSRF invece del bearer.

### `api_activity`

`api_activity.token` salva solo l'impronta del bearer (`sha256:` + 16 caratteri esadecimali), utile per correlare le chiamate dello stesso token. Prima salvava il bearer in chiaro: `forge update` svuota le righe storiche con `ApiActivityTokenMigration` (idempotente, conteggio in `api_activity_tokens`).

### Riuso fuori da `Endpoint`

```php
use Wonder\Api\Token\BearerVerifier;

$verified = BearerVerifier::make()->verify($bearer, ['api_internal_user'], $ip, $domain, allowPageToken: false);
// $verified->userId, $verified->authority, $verified->tokenId, $verified->expiresAt
```

Lancia `EndpointException` con codice `401` / `403`. Un modulo con utenti propri passa un `ApiUserProvider` al costruttore.

## Risposte

### JSON standard

```php
return Response::json([
    'success' => true,
    'status' => 200,
    'response' => 'ok',
]);
```

### Raw

Serve quando hai già il payload pronto.

```php
return Response::raw($jsonString, 200);
```

## Errori

`Handler::run()` uniforma automaticamente gli errori in questo formato:

```json
{
  "success": false,
  "status": 400,
  "response": "Messaggio errore"
}
```

Quindi nel file endpoint devi scrivere solo la logica utile.

## Chiamata con JS

Chiamate classiche

```javascript
API_CLIENT.post("/")
.then(data => console.log(data))
.catch(error => console.error(error));
```

Chiamata alle api di wonder-image/app:

```javascript
API_CLIENT_APP.post("/")
.then(data => console.log(data))
.catch(error => console.error(error));
```

## Errori comuni

### 1. Endpoint non trovato

Controlla:

- route in `route.api.php`
- path dichiarato in `Handler::run(...)`
- handler file esistente

### 2. `Bearer token mancante`

Stai chiamando un endpoint protetto senza header `Authorization`.

### 3. `404` HTML invece di JSON

Succede quando il path non matcha nessuna route API.

Prima registra la route, poi testa l'endpoint.

### 4. Usi ancora il vecchio `try/catch` in ogni file

Evitalo.

Se l'endpoint è nel modello nuovo, usa:

- `Endpoint`
- `Handler`
- `Response`

## Best practice

- Una route API deve puntare a un handler in `http/api/...`
- Il file handler deve contenere solo autenticazione e logica
- Usa `Handler::run()` per evitare boilerplate duplicato
- Usa `Response::json()` come default
- Usa `Response::raw()` solo quando hai davvero già il payload serializzato
- Tieni i path coerenti con il gruppo route
- Per gli URL interni usa i path canonici `/api/...` (`Path::$api`, `Path::$appApi`, `Path::$apiDT`), non i vecchi percorsi package-local `.php`
