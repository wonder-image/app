---
icon: key
---

# OAuth per le risorse MCP

Il framework è un authorization server OAuth 2.1 per le risorse MCP dei moduli
(per esempio `/api/gestionale/mcp`). Un client MCP compatibile (Claude,
ChatGPT, Cursor, Claude Code, ecc.) scopre il server da solo, si registra,
manda l'utente a fare login e consenso nel browser e poi chiama la risorsa con
un access token.

Gli endpoint esistono **solo se almeno un modulo registra una risorsa**. Senza
risorse non c'è nessuna rotta OAuth e il sito non espone superficie in più.

## Dichiarare una risorsa

Un modulo registra la risorsa con `OAuth::resource()` nel proprio file rotte
api, `config/routes/route.api.php`:

```php
\Wonder\OAuth\OAuth::resource('/api/<modulo>/mcp', '<Nome>');
```

Il file `route.api.php` del modulo viene caricato **prima** di
`route.oauth.php` (l'ordine è alfabetico), che costruisce le rotte in base alle
risorse già registrate.

### Scope

Il terzo argomento, facoltativo, elenca gli scope della risorsa con
l'etichetta da mostrare nella pagina di consenso:

```php
\Wonder\OAuth\OAuth::resource('/api/<modulo>/mcp', '<Nome>', [
    'catalog:read' => 'Leggere il catalogo',
    'catalog:write' => 'Modificare il catalogo',
]);
```

- `GET /oauth/authorize` accetta `scope` (separati da spazio). Uno scope che
  la risorsa non dichiara torna al client con `invalid_scope`; senza `scope`
  valgono tutti.
- Nella pagina di consenso gli scope sono caselle già spuntate: l'utente può
  toglierne. Se le toglie tutte è come annullare (`access_denied`).
- Il grant salva gli scope approvati e la risposta del token li restituisce
  in `scope`. Un nuovo consenso con scope diversi revoca il grant precedente
  e i suoi token, invece di cambiarne i poteri.
- Il refresh tiene gli scope del grant: un `scope` più largo risponde
  `invalid_scope`, uno più stretto non restringe il nuovo token.
- I metadati annunciano gli scope in `scopes_supported` (della risorsa e
  dell'authorization server).
- Senza scope dichiarati il parametro si ignora e il token vale quanto
  l'utente. Anche un grant nato prima degli scope vale tutti quelli della
  risorsa.

Il significato degli scope è della risorsa: il core li valida e li consegna
in `OAuthCaller::$scopes`, la risorsa decide cosa permettono.

- Non registrare la risorsa in `custom/routes`: si carica dopo e la rotta
  PRM (`/.well-known/oauth-protected-resource/...`) non verrebbe generata.
- Un catch-all frontend di un modulo (una rotta che accetta qualunque path)
  può oscurare `/oauth/*`: tenerlo più restrittivo o dichiararlo dopo.

## Proteggere l'handler

```php
try {
    $caller = \Wonder\OAuth\ResourceGuard::authenticate('/api/<modulo>/mcp');
} catch (\Wonder\OAuth\OAuthException $e) {
    $e->send(); // 401 + WWW-Authenticate: Bearer resource_metadata="…", error="invalid_token"
    exit;
}
```

`ResourceGuard::authenticate()` accetta solo bearer `wio_at_`, verifica
selector, validator, scadenza, revoca di token, grant e client, e che la
risorsa del grant coincida con quella richiesta (audience). Senza token il 401
contiene solo `resource_metadata`; con token non valido aggiunge
`error="invalid_token"`.

`OAuthCaller` contiene:

| Campo | Contenuto |
|---|---|
| `user` | utente del consenso (`infoUser()`: authority, ruoli, `active`) |
| `userId` | id dell'utente |
| `grantId` | id del grant, utile per i log |
| `clientId` | `client_id` del client registrato |
| `resource` | URL della risorsa del grant |
| `scopes` | scope approvati, ordinati; vuoto se la risorsa non ne dichiara |

Le autorizzazioni (ruoli, permessi, scope) **restano alla risorsa**: il guard
non controlla `allowed_ips`, `allowed_domains`, i ruoli né gli scope. Il token
non supera mai i permessi dell'utente che ha dato il consenso; gli scope
possono solo restringerli.

## Collegare un client

Nel backend la pagina **Impostazioni → Connessioni** (in fondo alla sidebar, accanto a Profilo ed Esci) mostra l'URL MCP da incollare, valido
per qualsiasi client MCP compatibile con OAuth (Claude, ChatGPT, Cursor, ecc.),
e l'elenco delle connessioni attive, che si possono revocare.

Per Claude Code:

```bash
claude mcp add --transport http <nome> <url>
```

Login e consenso vengono richiesti a ogni nuovo collegamento.

## Token

- Formato: `wio_at_<16 hex>.<64 hex>` (access) e `wio_rt_<16 hex>.<64 hex>`
  (refresh); `client_id` = `wio_client_` + 32 hex. Nel DB si salvano solo
  `selector` e `sha256(validator)`.
- Durate di default: access 1 ora, refresh 30 giorni, scadenza assoluta del
  grant 90 giorni dal consenso (vedi la tabella `.env`).
- Rotazione: ogni refresh restituisce una nuova coppia e consuma il vecchio
  refresh token.
- Riuso: un refresh già consumato revoca l'intero grant (access e refresh) e
  risponde `400 invalid_grant`.
- Revoca: da Impostazioni → Connessioni o con `POST /oauth/revoke`.
- Pulizia: il task notturno `wonder.oauth_cleanup` (03:30) revoca i grant
  scaduti e cancella i token vecchi di 7 giorni e i client inattivi da 30.

## Configurazione `.env`

Assente, vuota o non intera → default. Fuori limiti → valore limitato e un
`error_log` per richiesta. Letta da `Wonder\OAuth\Config`.

| Variabile | Default | Limiti |
|---|---|---|
| `OAUTH_REGISTER_RATE_LIMIT` | 20 | 0–1000, 0 = disattivo |
| `OAUTH_ACCESS_TOKEN_TTL` | 3600 | 300–86400 |
| `OAUTH_REFRESH_TOKEN_TTL` | 2592000 | 3600–7776000 |
| `OAUTH_GRANT_MAX_AGE` | 7776000 | 86400–31536000 |

Valori fissi: codice di autorizzazione 300 s, richiesta in sessione 600 s, al
massimo 10 richieste in sessione.

## Deploy

- `APP_URL` deve essere https (l'issuer è `APP_URL` senza slash finale, mai
  l'header `Host`). Fuori dall'ambiente locale, con un `APP_URL` non https gli
  endpoint rispondono 500 e scrivono il motivo in `error_log`.
- Dopo l'aggiornamento eseguire `php forge update`: crea le tabelle `oauth_*`,
  aggiunge al gestionale la colonna `mcp_logs.oauth_grant_id` e rigenera il
  blocco router dell'`.htaccess`.
- Le regole `.htaccess` mandano `/oauth` e `/.well-known/oauth-*` direttamente a
  `handler/index.php`, senza il 301 che aggiunge lo slash finale. Herd e Valet
  non richiedono modifiche.
- Per collegare claude.ai serve un host pubblico raggiungibile da Anthropic;
  un sito `.test` locale funziona con Claude Code e client sulla stessa macchina.

## Endpoint

| Endpoint | Funzione |
|---|---|
| `GET /.well-known/oauth-authorization-server` | metadata dell'authorization server (RFC 8414) |
| `GET /.well-known/oauth-protected-resource[/<path>]` | metadata della risorsa (RFC 9728, PRM) |
| `POST /oauth/register` | registrazione dinamica del client (RFC 7591), 201 |
| `GET /oauth/authorize` | avvio del flusso con PKCE S256, `resource` e `scope` facoltativo |
| `GET/POST /oauth/login` | login dell'utente |
| `GET/POST /oauth/consent` | consenso, poi redirect al client con `code` e `iss` |
| `POST /oauth/token` | `authorization_code` e `refresh_token` |
| `POST /oauth/revoke` | revoca di access o refresh token |

Tutte le rotte rispondono con e senza slash finale e con `Cache-Control:
no-store`.

## Prova di integrazione

`tests/OAuth/integration-herd.php` percorre l'intero flusso via HTTP contro un
sito reale:

```bash
WI_TEST_URL=https://<sito>.test WI_TEST_MCP_USER=@mcp WI_TEST_MCP_PASSWORD=… php tests/OAuth/integration-herd.php
```

Senza `WI_TEST_MCP_PASSWORD` salta. Il sito deve usare questo framework
(controllare il symlink di `vendor/wonder-image/app`).
