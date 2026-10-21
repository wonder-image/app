# Sicurezza HTTP, errori e log

Cosa fa il core per header, file non pubblici, errori e log, e cosa resta da
configurare sul server.

## Header di sicurezza

Per le risposte PHP gli header li emette il bootstrap
(`Wonder\Http\SecurityHeaders::apply()`, chiamato in `wonder-image.php`),
così valgono anche su nginx e Herd:

| Header | Valore |
|---|---|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `SAMEORIGIN` |
| `Content-Security-Policy` | `frame-ancestors 'self'` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(self)` |
| `Strict-Transport-Security` | `max-age=31536000`, solo con `APP_HSTS=true` e richiesta HTTPS (niente `preload`) |
| `Cache-Control` | `no-store, private` sul backend e sul frontend con utente autenticato |

Un header già impostato da una pagina (per esempio `X-Frame-Options: DENY`
dell'autorizzazione OAuth) resta quello della pagina. `Cache-Control` invece
viene sempre sostituito quando serve `no-store`. Le pagine anonime restano
cacheabili anche se stampano il token di pagina, perché il token è legato
alla sessione. `X-Powered-By` viene tolto.
`geolocation=(self)` lascia funzionare la navigazione di Google Maps sul sito.

`expose_php` si imposta solo in `php.ini` (`expose_php = Off`): da PHP il core
toglie l'header con `header_remove()`.

## Blocchi `.htaccess` gestiti da Forge

`php forge build` e `php forge update` mantengono questi blocchi a marcatori
nel `.htaccess` della root. Il resto del file (regole custom) non viene
toccato:

- `# WONDER SECURITY START` / `END`: gli stessi header per i file statici
  (`Header set`, che sostituisce senza duplicare), il deny dei file non
  pubblici e il 403 sulle cartelle interne.
  - File negati: `.env*`, `.git*`, `.ht*`, `*.log`, `*.sql`, `*.sql.gz`,
    `*.bak`, `composer.json`, `composer.lock`, `package.json`,
    `package-lock.json`, `skills-lock.json`, `sync-data.json`,
    `generator.conf`, `forge`.
  - Cartelle negate: `.git/`, `.audit/`, `storage/`, `state/`, `tests/`,
    `docs/`, `node_modules/`, `lang/`, `handler/storage/`.
  - `vendor/` è negata con due eccezioni:
    - gli asset statici dei pacchetti sotto `vendor/<vendor>/<pacchetto>/resources/assets/`,
      solo con estensione `css`, `js`, `map`, `woff`, `woff2`, `ttf`, `otf`,
      `eot`, `png`, `jpg`, `jpeg`, `gif`, `webp`, `avif`, `svg`, `ico`. Restano
      negati i `.php`, i nomi con doppia estensione script (`x.php.css`) e le
      cartelle con un punto nel nome;
    - la cartella del generatore xml-sitemaps,
      `vendor/wonder-image/app/vendor-static/xml-sitemaps/`. Il suo
      `generator.conf` resta negato per nome.
  - Funziona con Apache 2.2 e 2.4 (`Require all denied` oppure
    `Order allow,deny`).
- `# WONDER UPLOAD START` / `END` in `assets/upload/.htaccess`: niente
  esecuzione di PHP o CGI, niente indice delle cartelle, niente `.ht*`.
  Forge lo scrive solo se `assets/upload` esiste. Le righe custom dello
  stesso file restano.

Al primo aggiornamento Forge toglie il vecchio blocco `Header always set`
senza marcatori, che conteneva `X-XSS-Protection`, solo se è identico a
quello generato in passato.

`RemoveHandler`, `Options` e `php_flag` richiedono un `AllowOverride` che li
permetta. Se l'hosting non lo concede, Apache risponde 500 sull'intera
cartella: in quel caso chiedi all'hosting di abilitarlo.

## nginx e Herd

nginx ignora `.htaccess`. Su nginx servono regole equivalenti nel server
block. Lo snippet seguente non viene applicato da Forge:

```nginx
location ~ /\.(?!well-known/) { deny all; }
location ~ ^/(storage|state|tests|docs|node_modules|lang|handler/storage|\.audit)(/|$) { deny all; }
location ~ ^/vendor(/|$)(?![^/]+/[^/]+/resources/assets/(?:[^/.]+/)*(?![^/]*\.(?:php\d?|phtml|phar|pht|phps|cgi|pl|py|sh)(?:\.|$))[^/]+\.(?:css|js|map|woff2?|ttf|otf|eot|png|jpe?g|gif|webp|avif|svg|ico)$|wonder-image/app/vendor-static/xml-sitemaps(/|$)) { deny all; }
location ~ (^|/)(composer\.(json|lock)|package(-lock)?\.json|skills-lock\.json|sync-data\.json|generator\.conf|forge)$ { deny all; }
location ~ \.(log|sql|sql\.gz|bak)$ { deny all; }
location ^~ /assets/upload/ {
    location ~ \.(php\d?|phtml|phar|phps|pht|cgi|pl|py|sh)(\.|$) { deny all; }
}
```

nginx valuta le location con regex nell'ordine in cui compaiono e si ferma alla
prima che corrisponde: metti queste regole prima della location che passa i
`.php` a PHP-FPM, altrimenti un `.php` sotto `vendor/` viene eseguito.

Herd (Valet) in locale serve qualunque file statico della cartella del sito.
Le regole sopra valgono per i server pubblici.

## Debug e `display_errors`

`Debug::enabled()`:

1. Se c'è `APP_DEBUG`, decide lui: `1`, `true`, `on` o `yes` lo accendono,
   qualsiasi altro valore lo spegne.
2. Altrimenti il debug è acceso solo con `APP_ENV=local`.

Una richiesta da localhost o da una rete privata non accende più il debug.
Fuori dalla CLI, `display_errors` vale `1` solo con il debug acceso.
`log_errors` è sempre `1`.

## Errori 500 e `request_id`

Sui 500 delle API (`RouteDispatcher`, `Wonder\Api\Handler`, le API delle
Resource, `api/app/update`):

- `success`, `status` e `response` restano. Senza debug `response` diventa
  il generico `Errore interno.` (`UpdateRunner failed` per l'update). Con il
  debug acceso `response` contiene il messaggio dell'eccezione.
- La risposta aggiunge `request_id` e l'header `X-Request-Id`. Se il client
  manda un `X-Request-Id` che rispetta `^[A-Za-z0-9._-]{1,64}$`, viene
  riusato; altrimenti il core ne genera uno. Lo stesso id finisce nel log.
- I 4xx non cambiano.

Gli errori SQL (`Sql\Utility\Error`) lanciano `Sql\Utility\QueryException`.
Il messaggio contiene azione, tabella e errore del database, ma non la query.
La query è in `QueryException::query()` e il `Logger` la scrive nel contesto
del log. Il testo dell'errore MySQL può comunque citare un frammento della
query, per esempio `near '...'`.

## Log

`Logger` scrive in `ROOT/storage/logs/` e non usa mai la cartella corrente.
Per trovare la root prova, in ordine:

1. la costante `ROOT`;
2. `$GLOBALS['ROOT']`;
3. la prima cartella con `vendor/autoload.php`, risalendo dallo script in
   esecuzione.

Crea le cartelle con permessi `0750` e i file con `0640`. Se non riesce a
scrivere, ripiega su `error_log()` di PHP e non lancia eccezioni.
`db-connections.log` (con `DB_CONNECTION_LOG=true`) è ora in
`storage/logs/db-connections.log`. Anche `php forge build` crea `storage/logs`
con `0750` e le altre cartelle di runtime con `0755`. Le cartelle che esistono
già mantengono i loro permessi.

Redazione (`Wonder\App\LogRedactor`):

- Negli URI scritti nei log, i parametri `token`, `t`, `key`, `password`,
  `email` e `_csrf` diventano `***`.
- In `auth_log`, `value` è mascherato (`m***@example.com`) e
  `verification_sent_link` non viene più scritto.
- `identifier`, usato dal throttle dei login, resta in chiaro.

I log scritti prima di questa versione restano dove sono. Non vengono né
spostati né cancellati:

- `handler/storage/logs/`, quando `ROOT` non era definita e la cartella
  corrente era `handler/`;
- `ROOT/db-connections.log`.

Entrambi sono coperti dal deny `.htaccess`. Cancellali a mano quando non
servono più.
