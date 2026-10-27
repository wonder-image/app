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
`{"success": false, "status": 403, "response": "La pagina è scaduta o la richiesta non è valida. Ricarica la pagina e riprova."}`
(il testo della notifica `929`, nella lingua corrente) senza
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

### Pagine: sessione e CSRF, nessun bearer (`API_TOKEN`)

Le pagine frontend e backend (e l'anteprima del catalogo) stampano `const API_TOKEN = '';`: nessun bearer nell'HTML. La costante resta definita perché la lib costruisce `API_CLIENT` / `API_CLIENT_APP` con `new ApiClient(..., API_TOKEN)`. Prima la pagina stampava `ApiTokens::pageToken()`: un JWT di `@system` con scadenza, ma leggibile nel sorgente di ogni pagina e, nella forma senza `sid`, riutilizzabile fuori dal browser fino alla scadenza (issue #85).

Una chiamata senza `Authorization: Bearer` viene accettata da `Wonder\Api\Endpoint` come **accesso di pagina** quando:

- l'endpoint ammette il token di pagina (`allowPageToken`, default `true` per gli handler; per le route Resource solo con `apiSchema()->allowPageToken()`);
- la richiesta è dello stesso sito (`Wonder\Http\SameOrigin`) e c'è una sessione PHP attiva (`Endpoint::sessionRequestAllowed()`);
- se non è `GET`/`HEAD`, porta il token CSRF di sessione (header `X-WI-CSRF` dal meta `wi-csrf`, che `ApiClient` manda già, oppure `_csrf` nel body: `Csrf::verify()`).

L'accesso vale come l'utente `@system` (`Credentials::systemApiUser()`) e passa da `BearerVerifier::verifySession()`: utente e token di sistema attivi, authority dell'endpoint, IP e domini ammessi del token. `$CALL->isPageToken()` è `true`, quindi gli endpoint con `allowPageToken: false` (`/api/app/update/`, `/api/task/scheduler/`, `/api/task/sitemap/`) e le route Resource senza opt-in lo rifiutano con `401 Bearer token mancante!`. In `api_activity.token` si registra `session`.

Senza sessione, da un altro sito o senza CSRF su `POST` la risposta resta `401 Bearer token mancante!`. Un bearer esplicito ha sempre la precedenza.

Il **token di pagina** (`ApiTokens::pageToken()`, `typ: page`, `exp` con `API_PAGE_TOKEN_TTL`, legato a `@system` con `tid` + `tkh` e alla sessione con `sid`) resta valido per chi lo manda come bearer:

- **Rinnovo**: `POST /api/token/page/` (rotta `token.page`, area `api`) restituisce `{ "success": true, "token": "…", "expires_in": 3600 }`. Risponde solo a richieste same-origin, con sessione attiva e token CSRF; altrimenti `403`, `405` se non è POST. Con l'accesso di pagina non serve più: resta per compatibilità con i siti che lo chiamano.
- I token senza `sid` valgono fino alla loro `exp`, poi `401`.

Gli endpoint ammessi alle pagine restano pubblici di fatto: chiunque apra il sito ottiene sessione e CSRF. La protezione è contro il riuso fuori dal browser e le richieste da altri siti, non contro un visitatore; un endpoint che non deve essere chiamato dal browser passa `allowPageToken: false`.

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
