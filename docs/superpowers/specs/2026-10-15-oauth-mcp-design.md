# OAuth 2.1 per le risorse MCP dei moduli — design

**Data:** 2026-10-15
**Repo:** app (framework). Satellite toccato: gestionale (adattamento di `Auth::fromRequest`).
**Tipo:** architetturale

## Contesto

La richiesta dell'utente:

> Predisponiamo OAuth 2.1 su wonder-image/app

L'obiettivo è poter aggiungere l'MCP del gestionale, e in futuro le risorse MCP di altri moduli, come **connettore remoto** su claude.ai e su qualunque altro client MCP conforme alla specifica di autorizzazione MCP. Oggi l'MCP del gestionale (`gestionale/http/api/mcp.php`) accetta solo un bearer JWT statico di `api_users`, da incollare a mano nel client. claude.ai non permette di farlo: per un connettore remoto richiede il flusso OAuth.

Il lavoro va nel framework e non nel gestionale perché server di autorizzazione, login e consenso sono comuni a tutti i moduli. Un modulo dichiara solo la propria risorsa protetta.

**Decisioni dell'utente:**
1. **Permessi del token.** Il token eredita l'utente che ha dato il consenso, di qualunque area (frontend, backend o altro). A limitarlo sono le autorizzazioni dell'utente, controllate dalla risorsa (per esempio i ruoli backend nei tool del gestionale). Non esistono scope funzionali.
2. **Login.** C'è una sola pagina di login OAuth, `/oauth/login`, valida per qualunque utente attivo indipendentemente dall'area.
3. **Pagina "Connessioni MCP"** nel backend. Mostra le connessioni attive con la possibilità di revocarle, e **il link da usare come MCP**, valido per qualunque client e non solo per Claude.
4. **Approccio A:** implementazione interna con token opachi, sul modello di `Wonder\Auth\OneTimeToken`. `league/oauth2-server` è stato scartato: porta con sé JWT, chiavi RSA e un'astrazione di repository che duplica quella dei Model, senza coprire DCR né i metadata RFC 9728.
5. I problemi di sicurezza trovati fuori scope sono diventati issue separate (vedi "Fuori scope").

## Requisiti esterni

Specifica di riferimento: **MCP Authorization 2025-06-18**. Ne derivano questi requisiti:

- **RFC 9728, Protected Resource Metadata (PRM).** Il server MCP risponde `401` con `WWW-Authenticate: Bearer resource_metadata="<url PRM>"`. Il PRM indica `authorization_servers`.
- **RFC 8414, Authorization Server Metadata.**
- **RFC 7591, Dynamic Client Registration (DCR).** claude.ai e Claude Code si registrano da soli come client pubblici (`token_endpoint_auth_method: none`).
- **OAuth 2.1:** solo authorization code con **PKCE S256** obbligatorio, `S256` dichiarato in `code_challenge_methods_supported`, nessun implicit né password grant, redirect URI confrontati in modo esatto.
- **RFC 8707:** parametro `resource` su authorize e token. Il token è legato a quella risorsa (audience) e la risorsa rifiuta token emessi per altre.
- **Refresh token ruotati** a ogni uso, perché i client sono pubblici.

Comportamenti specifici di Claude, da rispettare per compatibilità ma senza logica dedicata:

- Il callback di claude.ai è `https://claude.ai/api/mcp/auth_callback`; in futuro può essere anche `claude.com`. Con DCR arriva comunque nei `redirect_uris` registrati.
- Claude Code usa `http://localhost:<porta>/callback` e `http://127.0.0.1:<porta>/callback` con porta variabile. Per gli URI di loopback il confronto **ignora la porta** (RFC 8252 §7.3).
- Viene usato solo il **primo** `authorization_servers` del PRM.
- Il `resource` del PRM deve coincidere **esattamente** con l'URL inserito dall'utente.
- Discovery: prima `/.well-known/oauth-protected-resource/<path della risorsa>`, poi la radice `/.well-known/oauth-protected-resource`.
- `/token` riceve `application/x-www-form-urlencoded`; `/register` riceve JSON.
- Un refresh non valido deve rispondere `400 invalid_grant`. `401 invalid_client` fa ripartire la registrazione da capo.
- Timeout lato client: 10 s, 30 s per il refresh.
- Le chiamate di claude.ai arrivano da `160.79.104.0/21`. Il dato è solo informativo: non c'è nessun filtro IP.

## Architettura

```
Client MCP (claude.ai, Claude Code, …)
   │ 1. POST /api/<modulo>/mcp senza token  → 401 + WWW-Authenticate resource_metadata
   │ 2. GET /.well-known/oauth-protected-resource/api/<modulo>/mcp  → PRM
   │ 3. GET /.well-known/oauth-authorization-server  → AS metadata
   │ 4. POST /oauth/register  → client_id
   │ 5. browser: GET /oauth/authorize → /oauth/login → /oauth/consent → redirect con code
   │ 6. POST /oauth/token (authorization_code + PKCE + resource) → access + refresh
   │ 7. POST /api/<modulo>/mcp con Bearer wio_at_…  → ResourceGuard → modulo
   │ 8. POST /oauth/token (refresh_token) → nuova coppia, vecchio refresh consumato
```

Tutti gli endpoint OAuth sono **attivi solo se almeno un modulo registra una risorsa protetta**. Senza risorse, `/.well-known/*` e `/oauth/*` rispondono 404 e il sito non espone superficie in più.

### Unità

Il codice nuovo sta in `class/OAuth/` (namespace `Wonder\OAuth`). I Model stanno in `class/App/Models/OAuth/`.

| Unità | Responsabilità |
|---|---|
| `OAuth` (facade) | `OAuth::resource(string $path, string $name)`: registrazione della risorsa dai moduli. `OAuth::enabled()`. |
| `ResourceRegistry` | Elenco delle risorse: path, nome e URL canonico calcolato da `APP_URL`. |
| `Metadata` | Costruisce il JSON del PRM e dei metadata AS. Issuer = `APP_URL`, mai l'header `Host`. |
| `ClientRegistry` | DCR: valida e salva i client; lookup; `touch`. |
| `RedirectUri` | Valida e confronta i redirect URI (https o loopback; porta ignorata solo sul loopback). |
| `Pkce` | Verifica S256. |
| `AuthorizationRequest` | Valida i parametri di `/oauth/authorize` e salva la richiesta in sessione con id e TTL. |
| `AuthorizationCode` | Emette e consuma il codice tramite `OneTimeToken` (purpose `oauth_code`). |
| `TokenIssuer` | Crea grant e token; ruota il refresh; rileva il riuso. |
| `TokenEndpoint` | Gestisce `/oauth/token` e mappa gli errori RFC 6749. |
| `ResourceGuard` | `ResourceGuard::authenticate(string $resource): OAuthCaller` per i moduli. |
| `OAuthCaller` | Oggetto immutabile: `user` (da `infoUser`), `userId`, `grantId`, `clientId`, `resource`. |
| `Cleanup` | Task dello Scheduler che pulisce token e client scaduti. |
| `Config` | Legge dal `.env` i valori configurabili (rate limit e durate), con default e limiti. Vedi "Configurazione". |
| `OAuthException` | Errore con `error`, `error_description`, status HTTP. |
| `Store\OAuthStore` | Interfaccia di persistenza: `SqlStore` (mysqli + `OneTimeToken`) in produzione, `MemoryStore` nei test. Le altre unità la ricevono nel costruttore o da `OAuth::store()`. |

Pagine e handler HTTP stanno in `app/http/oauth/` e `app/http/backend/oauth/`; le viste in `app/view/pages/oauth/` e `app/view/pages/backend/oauth/`. Le rotte vanno in un file `app/config/routes/route.oauth.php`, caricato dal `RouteDispatcher` solo se `OAuth::enabled()`.

## Endpoint

Tutte le risposte di `/oauth/*` e `/.well-known/*` inviano `Cache-Control: no-store` e `Pragma: no-cache`. Le rotte rispondono **con e senza slash finale, senza redirect 301**: un redirect su un POST fa perdere il corpo e alcuni client non lo seguono.

### `GET /.well-known/oauth-protected-resource/<path>`

Una rotta per ogni risorsa registrata, generata dal registry. Risponde anche la radice `/.well-known/oauth-protected-resource`, con la prima risorsa registrata come fallback di discovery.

```json
{
  "resource": "https://sito.it/api/gestionale/mcp",
  "authorization_servers": ["https://sito.it"],
  "bearer_methods_supported": ["header"],
  "resource_name": "Gestionale"
}
```

`resource` è l'URL canonico: `APP_URL` più il path dichiarato, senza slash finale. È l'URL mostrato nella pagina Connessioni MCP, quindi coincide con quello che l'utente incolla nel client.

### `GET /.well-known/oauth-authorization-server`

```json
{
  "issuer": "https://sito.it",
  "authorization_endpoint": "https://sito.it/oauth/authorize",
  "token_endpoint": "https://sito.it/oauth/token",
  "registration_endpoint": "https://sito.it/oauth/register",
  "revocation_endpoint": "https://sito.it/oauth/revoke",
  "response_types_supported": ["code"],
  "grant_types_supported": ["authorization_code", "refresh_token"],
  "code_challenge_methods_supported": ["S256"],
  "token_endpoint_auth_methods_supported": ["none"],
  "revocation_endpoint_auth_methods_supported": ["none"],
  "scopes_supported": []
}
```

`offline_access` non è dichiarato: il refresh token viene emesso sempre.

### `POST /oauth/register` (RFC 7591)

Riceve JSON. Regole:

- `redirect_uris` è obbligatorio, con da 1 a 10 elementi. Ogni URI deve essere `https://` oppure loopback `http://localhost` / `http://127.0.0.1` / `http://[::1]`, senza fragment e senza userinfo.
- `token_endpoint_auth_method`: solo `none`. Altri valori ricevono `400 invalid_client_metadata`.
- `grant_types` ⊆ {`authorization_code`, `refresh_token`}; `response_types` ⊆ {`code`}.
- `client_name`: facoltativo, al massimo 100 caratteri, ripulito da tag e caratteri di controllo. Se manca si usa l'host del primo redirect URI.
- Rate limit per IP: `Config::registerRateLimit()` registrazioni l'ora, 20 di default, `0` per disattivarlo. Il framework non ha un rate limiter, quindi il limite si calcola contando le righe di `oauth_clients` con lo stesso `registration_ip` create nell'ultima ora. Oltre la soglia: `429`.
- Risposta `201` con `client_id` (`wio_client_` + 32 hex casuali), `client_id_issued_at`, i metadati normalizzati e nessun `client_secret`.

### `GET /oauth/authorize`

Parametri obbligatori: `response_type=code`, `client_id`, `redirect_uri`, `code_challenge`, `code_challenge_method=S256`, `resource`. Facoltativi: `state` e `scope`; `scope` viene ignorato e non restituito.

Sequenza di validazione:

1. Client sconosciuto o revocato, oppure `redirect_uri` non registrato: viene mostrata una **pagina di errore** e **non si fa nessun redirect**, perché il redirect URI non è affidabile.
2. Da qui in poi gli errori fanno redirect a `redirect_uri?error=…&state=…&iss=…`:
   - `response_type` diverso da `code` → `unsupported_response_type`;
   - PKCE mancante o metodo diverso da S256 → `invalid_request`;
   - `resource` mancante o non registrato → `invalid_target`.
3. La richiesta valida viene salvata in `$_SESSION['oauth_requests'][<id>]`, con id di 32 hex casuali e TTL di 10 minuti. In sessione restano al massimo 10 richieste; le più vecchie vengono scartate.
4. Se l'utente in sessione è attivo, si va a `/oauth/consent?req=<id>`, altrimenti a `/oauth/login?req=<id>`.

### `GET|POST /oauth/login?req=<id>`

- Login unico per qualunque utente attivo, di qualunque area. Riusa `authenticateUserLogin()` e quindi `AuthLog`, `logFailedLogin`, `session_regenerate_id` e le regole già esistenti sul login. Area di log: `oauth`.
- La destinazione dopo il login è **sempre** `/oauth/consent?req=<id>`. Non esiste un parametro di redirect libero.
- Il form usa la protezione CSRF di `Wonder\Http\Csrf`.
- Con `req` mancante o scaduto: pagina "Richiesta scaduta, riprova dal tuo client".
- Il layout è minimale e neutro, con il logo e il nome del sito e senza la navigazione del frontend.

### `GET|POST /oauth/consent?req=<id>`

La pagina mostra:
- il nome del client;
- l'host del redirect URI, con un avviso esplicito se è loopback ("un'applicazione sul tuo computer");
- il nome della risorsa;
- il nome del sito;
- l'utente collegato, con il link "Cambia account" che fa logout e torna a login con lo stesso `req`.

Il consenso viene chiesto **ogni volta**, senza memorizzarlo: chi aggiunge un connettore lo fa raramente e una conferma esplicita protegge da flussi avviati da terzi.

- **Approva** (POST con CSRF): crea o riusa il grant `(user_id, client_id, resource)` non revocato, emette il codice e fa redirect a `redirect_uri?code=…&state=…&iss=<issuer>`. La richiesta viene rimossa dalla sessione.
- **Nega**: redirect con `error=access_denied`.
- Prima di emettere il codice viene ricontrollato che l'utente sia attivo.

### `POST /oauth/token`

Riceve form-urlencoded. Gli errori sono JSON `{"error","error_description"}` con status 400, oppure 401 per `invalid_client`.

**`grant_type=authorization_code`**
- Parametri: `code`, `redirect_uri`, `client_id`, `code_verifier`, `resource`.
- Il codice viene consumato in modo atomico (`OneTimeToken::consume`), quindi è monouso. Un codice consumato, scaduto o inesistente dà `invalid_grant`.
- Deve coincidere con il codice: `client_id`, `redirect_uri` (confronto esatto con quello usato su authorize) e `resource` → altrimenti `invalid_grant`. Il `resource` può mancare se il client non lo rimanda; in quel caso vale quello del codice.
- PKCE: `BASE64URL(SHA256(code_verifier)) === code_challenge`, con `hash_equals`; `code_verifier` deve avere tra 43 e 128 caratteri `[A-Za-z0-9-._~]` → altrimenti `invalid_grant`.
- Client inesistente o revocato → `401 invalid_client`.
- Utente non più attivo → `invalid_grant`.

**`grant_type=refresh_token`**
- Parametri: `refresh_token`, `client_id`, `resource` facoltativo (se presente deve coincidere con quello del grant → altrimenti `invalid_target`).
- Refresh valido: viene consumato, se ne emette uno nuovo insieme a un nuovo access token, e il grant resta lo stesso.
- **Rilevamento del riuso:** un refresh già consumato revoca l'intero grant, con tutti i suoi access e refresh token, e risponde `invalid_grant`.
- Refresh scaduto o revocato, grant revocato, utente non attivo → `invalid_grant`.
- Client cancellato → `401 invalid_client`.

Risposta di successo:

```json
{
  "access_token": "wio_at_…",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "wio_rt_…"
}
```

### `POST /oauth/revoke` (RFC 7009)

Parametri: `token`, `client_id`, `token_type_hint` facoltativo. Revoca il **grant** a cui appartiene il token, se il `client_id` corrisponde. Risponde sempre `200`, anche per token sconosciuti.

## Token e dati

### Formato dei token

- I token sono opachi: `wio_at_<selector>.<validator>` per l'access, `wio_rt_<selector>.<validator>` per il refresh.
- `selector` sono 16 hex, `validator` 64 hex, generati con `random_bytes`.
- Nel DB si salvano solo `selector` e `sha256(validator)`; la verifica usa `hash_equals`.
- **Non sono mai JWT** e non dipendono da `APP_KEY`: ruotare `APP_KEY` non li invalida, revocarli sì.
- Durata, configurabile (vedi "Configurazione"): access 1 ora, refresh 30 giorni, scadenza assoluta del grant 90 giorni dall'ultimo consenso.

Il codice di autorizzazione è un `OneTimeToken` con purpose `oauth_code`, TTL 5 minuti, subject `user_id`. In `metadata_json` ci sono `client_id`, `redirect_uri`, `code_challenge`, `resource` e `grant_id`.

### Tabelle

I Model stanno in `class/App/Models/OAuth/` e vengono scoperti da `ModelRegistry`. Le tabelle sono create da `php forge update`. Tutte hanno anche le colonne standard del framework (`id`, `creation`, `last_modified`, `deleted`).

| Tabella | Colonne |
|---|---|
| `oauth_clients` | `client_id` (unique), `client_name`, `redirect_uris` (json), `token_endpoint_auth_method`, `client_secret_hash` (null; predisposto per client confidenziali futuri), `registration_ip`, `last_used_at`, `revoked_at` |
| `oauth_grants` | `user_id`, `client_id`, `resource`, `scope` (vuoto), `consented_at`, `last_used_at`, `revoked_at` |
| `oauth_access_tokens` | `grant_id`, `selector` (unique), `validator_hash`, `expires_at`, `revoked_at` |
| `oauth_refresh_tokens` | `grant_id`, `selector` (unique), `validator_hash`, `expires_at`, `consumed_at`, `revoked_at` |

Gli indici sono su `selector`, `grant_id`, `(user_id, client_id, resource)`, `expires_at`.

### Pulizia

Un task dello Scheduler, ogni notte:
- elimina i token scaduti, consumati o revocati da più di 7 giorni;
- elimina i client DCR senza grant attivi e non usati da 30 giorni;
- revoca i grant oltre la scadenza assoluta.

## API per i moduli

Un modulo dichiara la propria risorsa nel bootstrap, per esempio nel file rotte o in un service provider del modulo:

```php
\Wonder\OAuth\OAuth::resource('/api/gestionale/mcp', 'Gestionale');
```

Nell'handler della risorsa:

```php
try {
    $caller = \Wonder\OAuth\ResourceGuard::authenticate('/api/gestionale/mcp');
} catch (\Wonder\OAuth\OAuthException $e) {
    $e->send(); // 401 + WWW-Authenticate: Bearer resource_metadata="…", error="invalid_token"
    exit;
}
$caller->user;      // infoUser(): authority, ruoli, active…
$caller->grantId;
$caller->clientId;
```

Cosa fa `ResourceGuard::authenticate`:
- legge `Authorization: Bearer`, con fallback su `REDIRECT_HTTP_AUTHORIZATION` e `getallheaders()`, come `Endpoint::checkToken`;
- accetta solo token `wio_at_`;
- verifica selector, validator, scadenza e revoca del token, revoca del grant e del client, e che `grant.resource` coincida con la risorsa richiesta (**audience**);
- verifica che l'utente esista e sia `active == 'true'`;
- aggiorna `last_used_at` di grant e client al massimo una volta al minuto;
- **non** controlla `allowed_ips` e `allowed_domains`, che riguardano i token `api_users`, né le autorizzazioni: queste restano compito della risorsa.

Senza token, `ResourceGuard` risponde `401` con il solo `resource_metadata`, senza `error`. Con un token non valido aggiunge `error="invalid_token"`.

## Configurazione

I valori operativi sono modificabili da `.env` del progetto, senza toccare il codice. Li legge `Wonder\OAuth\Config` dopo `Credentials::loadEnv()`.

| Chiave `.env` | Default | Limiti | Significato |
|---|---|---|---|
| `OAUTH_REGISTER_RATE_LIMIT` | `20` | `0`–`1000` | Registrazioni DCR per IP all'ora. `0` disattiva il limite. |
| `OAUTH_ACCESS_TOKEN_TTL` | `3600` | `300`–`86400` | Durata dell'access token, in secondi. |
| `OAUTH_REFRESH_TOKEN_TTL` | `2592000` (30 giorni) | `3600`–`7776000` | Durata del refresh token, in secondi. |
| `OAUTH_GRANT_MAX_AGE` | `7776000` (90 giorni) | `86400`–`31536000` | Scadenza assoluta del grant dall'ultimo consenso, in secondi. |

Regole:
- Un valore assente, vuoto o non intero usa il default.
- Un valore fuori dai limiti viene riportato al limite più vicino e scrive un avviso in `error_log` una volta per richiesta.
- `expires_in` nella risposta di `/oauth/token` riflette sempre il valore effettivo.
- Una modifica vale per i token emessi da quel momento; quelli già emessi mantengono la scadenza salvata.

Restano fissi, perché sono parametri di sicurezza del protocollo: TTL del codice (5 minuti), TTL della richiesta in sessione (10 minuti), soglie di pulizia.

Le chiavi sono documentate in `docs/app/piattaforma/oauth.md`.

## Pagina backend "Connessioni MCP"

La rotta è `backend.oauth.connections`, visibile a ogni utente backend collegato. Compare nel menu del backend solo se `OAuth::enabled()`.

**Sezione "Server MCP disponibili"**, una card per ogni risorsa registrata:
- nome e URL canonico, con il bottone "Copia";
- istruzioni generiche valide per qualunque client MCP: "Aggiungi un server MCP remoto (HTTP streamable) con questo URL. Il client aprirà il browser per l'accesso e la conferma.";
- un esempio per Claude Code: `claude mcp add --transport http <nome> <url>`, senza header.

**Sezione "Connessioni attive"**: tabella dei grant non revocati, con client, dominio del redirect, risorsa, data del consenso, ultimo uso e bottone **Revoca** (POST con CSRF).
- L'utente vede i propri grant.
- `admin` e `administrator` vedono quelli di tutti, con una colonna Utente.

Le stringhe stanno nei lang del framework, `it` ed `en`.

## Adattamento del gestionale

- Registra la risorsa: `OAuth::resource('/api/gestionale/mcp', 'Gestionale')`.
- `Auth::fromRequest`:
  - un bearer che inizia con `wio_at_` passa a `ResourceGuard::authenticate`, e il `Caller` viene costruito dalle authority dell'utente;
  - un bearer JWT segue **il percorso attuale senza modifiche**, quindi le integrazioni esistenti con token `api_users` continuano a funzionare.
- `Caller`: `apiUserId` diventa nullable e si aggiunge `oauthGrantId` (nullable). `McpLog` registra quello dei due che è valorizzato.
- Il `401` di `http/api/mcp.php` aggiunge `WWW-Authenticate: Bearer resource_metadata="…"`.
- I tool continuano a controllare i ruoli backend: un utente frontend autorizzato via OAuth vedrà i tool rifiutare con l'errore attuale. È il comportamento voluto dalla decisione 1.
- Aggiornare `docs/dev/concetti/mcp.md`.

## Deploy e infrastruttura

- **`.htaccess`:** le regole che aggiungono lo slash finale con un 301 colpirebbero `/.well-known/…` e `/oauth/token`. Prima di quelle regole va aggiunta un'eccezione che manda `^/?\.well-known/` e `^/?oauth/` direttamente a `handler/index.php`. Va fatto nei tre punti che generano la configurazione:
  - `Build.php` (righe 179-239 circa);
  - `app/build/update/configuration_file.php` (righe 9-27 circa);
  - lo stub di `WonderValetDriver.php` per Herd e Valet **non richiede modifiche**: non aggiunge lo slash finale e manda già a `handler/index.php` ogni path che non è un file.
- **CORS** dell'area api:
  - si aggiungono `Mcp-Protocol-Version` e `Mcp-Session-Id` agli allow-headers;
  - si aggiunge `Access-Control-Expose-Headers: WWW-Authenticate, Mcp-Session-Id`.
  - `/.well-known/*` e `/oauth/token|register|revoke` rispondono con CORS `*` e gestiscono `OPTIONS`, perché alcuni client web fanno discovery dal browser.
- **Tabelle:** create da `php forge update`, senza migrazioni manuali.
- **HTTPS:** in produzione l'issuer deve essere https. Se `APP_URL` non è https e l'ambiente non è locale, gli endpoint rispondono 500 con un messaggio chiaro nei log.

## Errori e sicurezza

- Errori OAuth nel formato RFC 6749 (`error`, `error_description`), senza dettagli interni.
- I token non vengono mai loggati né salvati in chiaro, e non compaiono in `api_activity`.
- Ogni confronto di segreti usa `hash_equals`.
- Il casuale viene solo da `random_bytes` / `bin2hex(random_bytes())`. **Non** si usa `Support/Text/Random.php`, che non è un CSPRNG.
- Login e consenso sono protetti con CSRF; la sessione viene rigenerata dopo il login.
- Nessun redirect fuori dagli URI registrati.
- Errori prima della validazione del client: pagina di errore, mai redirect.
- `iss` viene incluso nella risposta di authorize (RFC 9207) contro il mix-up.
- Pagine login e consenso: `X-Frame-Options: DENY` e `Content-Security-Policy: frame-ancestors 'none'` contro il clickjacking.

## Test

Script `php tests/OAuth/*.php` con `tests/harness.php`:
- `PkceTest`: vettori dell'appendice B di RFC 7636; verifier troppo corto, troppo lungo o con caratteri non ammessi.
- `RedirectUriTest`: https esatto; loopback con porta diversa accettato; host diverso, path diverso, fragment, userinfo e `http` non loopback rifiutati.
- `ClientRegistrationTest`: metadati validi e non validi, `token_endpoint_auth_method` diverso da `none`, `client_name` sanificato.
- `AuthorizationCodeTest`: codice monouso; mismatch di client, redirect o resource; codice scaduto.
- `ConfigTest`: default; valori validi; valori non interi; valori fuori dai limiti riportati al limite; `0` sul rate limit.
- `TokenIssuerTest`: rotazione del refresh; riuso → grant revocato; utente disattivato → `invalid_grant`; client cancellato → `invalid_client`.
- `ResourceGuardTest`: token valido; scaduto; revocato; audience diversa → rifiuto; formato JWT → rifiuto.
- `MetadataTest`: JSON di PRM e AS; issuer da `APP_URL` anche con header `Host` diverso; header `WWW-Authenticate`.

Test di integrazione HTTP contro Herd (`https://<sito>.test`): discovery, register, authorize con sessione simulata, token, chiamata alla risorsa, refresh e revoca.

Nel gestionale, `tests/integrazione/McpHttpTest.php` viene esteso con il percorso `wio_at_`, mentre il percorso JWT resta invariato.

Il test end-to-end reale con claude.ai richiede un host pubblico (staging o tunnel cloudflared). Claude Code si può provare in locale.

## Documentazione

- Nuova pagina `docs/app/piattaforma/oauth.md`, linkata da `docs/app/SUMMARY.md`, che spiega:
  - come un modulo dichiara una risorsa;
  - come funziona `ResourceGuard`;
  - come un utente collega un client;
  - durate e revoca dei token;
  - le chiavi `.env` di configurazione;
  - requisiti di deploy.
- Aggiornare `gestionale/docs/dev/concetti/mcp.md`.

## Fuori scope

Problemi trovati durante l'analisi, tracciati come issue separate:
- [wonder-image/app#83](https://github.com/wonder-image/app/issues/83): `Endpoint::verifyToken` non confronta il bearer con `api_users.token`.
- [wonder-image/app#84](https://github.com/wonder-image/app/issues/84): i JWT di `api_users` non hanno `exp`.
- [wonder-image/app#85](https://github.com/wonder-image/app/issues/85): token di sistema esposto nell'HTML come `const API_TOKEN`.
- [wonder-image/app#86](https://github.com/wonder-image/app/issues/86): `api_activity` salva i bearer in chiaro.
- [wonder-image/app#87](https://github.com/wonder-image/app/issues/87): open redirect nel login backend tramite `?redirect=`.
- [wonder-image/app#88](https://github.com/wonder-image/app/issues/88): supporto ai Client ID Metadata Document (CIMD).
- [wonder-image/app#89](https://github.com/wonder-image/app/issues/89): login federato su `/oauth/login`.

Restano esclusi anche:
- scope funzionali e permessi per singolo tool;
- client confidenziali e `client_credentials`;
- token JWT o introspection (RFC 7662);
- consenso memorizzato.
