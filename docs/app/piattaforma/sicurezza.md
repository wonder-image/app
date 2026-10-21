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
`geolocation` vale `(self)` e non `()`: la navigazione del componente
GoogleMap (`MapNavigator.js` della lib wonder-image) chiama
`navigator.geolocation.watchPosition()`, che con `()` verrebbe bloccata
anche sul sito stesso. `(self)` la consente solo all'origine del sito, non
agli iframe di terze parti.

`expose_php` si imposta solo in `php.ini` (`expose_php = Off`): da PHP il core
toglie l'header con `header_remove()`.

## Blocchi `.htaccess` gestiti da Forge

`php forge build` e `php forge update` mantengono questi blocchi a marcatori
nel `.htaccess` della root. Il resto del file (regole custom) non viene
toccato:

- `# WONDER SECURITY START` / `END`: gli stessi header per i file statici
  (`Header set`, che sostituisce senza duplicare), il deny dei file non
  pubblici e il 403 sulle cartelle interne.
  - File negati, senza distinzione di maiuscole: `.env*`, `.git*`, `.ht*`,
    `*.log`, `*.log.N`, `*.sql`, `*.sql.gz`, `*.bak`, `*.old`, `*.orig`,
    `*.swp`, `*~`, `composer.json`, `composer.lock`, `package.json`,
    `package-lock.json`, `skills-lock.json`, `sync-data.json`,
    `generator.conf`, `forge`.
  - Archivi negati fuori da `assets/upload/`: `*.zip`, `*.tar`, `*.tar.gz`,
    `*.tgz`. Gli zip caricati dal backend restano scaricabili.
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
  I file `.svg` e `.svgz` escono con `Content-Security-Policy "sandbox"` e
  `X-Content-Type-Options "nosniff"` (serve `mod_headers`): uno script
  rimasto nel file non gira nemmeno aperto da solo nel browser.
  Forge lo scrive solo se `assets/upload` esiste. Le righe custom dello
  stesso file restano.

Al primo aggiornamento Forge toglie il vecchio blocco `Header always set`
senza marcatori, che conteneva `X-XSS-Protection`, solo se è identico a
quello generato in passato. Se è stato modificato resta e va tolto a mano:
`forge update` stampa un avviso per ogni `Header always set` fuori dai
blocchi `WONDER`.

Un marcatore START senza END (o END senza START) viene tolto prima di
aggiornare il blocco; le righe intorno restano. Su un file con terminazioni
CRLF il blocco viene scritto in CRLF.

`RemoveHandler`, `Options` e `php_flag` richiedono un `AllowOverride` che li
permetta. Se l'hosting non lo concede, Apache risponde 500 sull'intera
cartella: in quel caso chiedi all'hosting di abilitarlo.

## nginx e Herd

nginx ignora `.htaccess`. Su nginx servono regole equivalenti nel server
block. Lo snippet seguente non viene applicato da Forge:

```nginx
location ~ /\.(?!well-known/) { deny all; }
location ~* ^/(storage|state|tests|docs|node_modules|lang|handler/storage|\.audit)(/|$) { deny all; }
location ~* ^/vendor/wonder-image/app/vendor-static/xml-sitemaps/data/(?!.*\.(txt|html|gz|jpe?g|png|gif)$) { deny all; }
location ~* ^/vendor/wonder-image/app/vendor-static/xml-sitemaps/(pages/|.*\.conf$) { deny all; }
location ~* ^/vendor(/|$)(?![^/]+/[^/]+/resources/assets/(?:[^/.]+/)*(?![^/]*\.(?:php\d?|phtml|phar|pht|phps|cgi|pl|py|sh)(?:\.|$))[^/]+\.(?:css|js|map|woff2?|ttf|otf|eot|png|jpe?g|gif|webp|avif|svg|ico)$|wonder-image/app/vendor-static/xml-sitemaps(/|$)) { deny all; }
location ~* (^|/)(composer\.(json|lock)|package(-lock)?\.json|skills-lock\.json|sync-data\.json|generator\.conf|forge)$ { deny all; }
location ~* (\.(log(\.\d+)?|sql(\.gz)?|bak|old|orig|swp)|~)$ { deny all; }
location ~* ^/(?!assets/upload/).*\.(zip|tgz|tar(\.gz)?)$ { deny all; }
location ^~ /assets/upload/ {
    location ~* \.(php\d?|phtml|phar|phps|pht|cgi|pl|py|sh)(\.|$) { deny all; }
}
```

Su Apache la cartella `data/` di xml-sitemaps è protetta dal suo `.htaccess`
(solo `txt`, `html`, `gz` e immagini). nginx lo ignora, quindi le due regole
su `xml-sitemaps` ne fanno le veci e negano anche `pages/` e `default.conf`.

nginx valuta le location con regex nell'ordine in cui compaiono e si ferma alla
prima che corrisponde: metti queste regole prima della location che passa i
`.php` a PHP-FPM, altrimenti un `.php` sotto `vendor/` viene eseguito.

Herd (Valet) in locale serve qualunque file statico della cartella del sito.
Le regole sopra valgono per i server pubblici.

## Upload

`uploadFiles()` controlla ogni file con
`Wonder\App\Support\UploadValidator::checkFormat()` prima di spostarlo:

- `extensions` esplicito nel campo (modello o input): l'estensione deve
  essere nella lista, altrimenti ALERT 921. Le estensioni del modello
  valgono anche quando l'input dichiara un tipo (`fileDragDrop('image')`).
- `extensions` vuoto e tipo dell'input noto: vale la lista del tipo.
  `image` = `png`, `jpg`, `jpeg`, `webp`; `video` = `mp4`; `gallery` =
  l'unione; `pdf`, `png`, `jpg`, `webp`, `svg` = sé stessi.
- `extensions` vuoto e tipo ignoto: è negata la lista fissa `php`,
  `php3`-`php8`, `phtml`, `phar`, `pht`, `phps`, `html`, `htm`, `xhtml`,
  `xht`, `shtml`, `mht`, `js`, `mjs`, `svg`, `svgz`, `xml`, `xsl`, `xslt`,
  `cgi`, `pl`, `py`, `sh`, `htaccess` e un file senza estensione (ALERT 921).
- `svgz` è sempre rifiutato (ALERT 921), anche se dichiarato: il contenuto
  compresso non passa dal sanitizer.
- Poi `finfo`: per le estensioni note il MIME deve corrispondere (un `.png`
  che contiene PHP non passa), per le altre si rifiuta solo un contenuto
  PHP, HTML, JavaScript o shell. In entrambi i casi ALERT 927. Il contenuto
  dei file validi non viene analizzato oltre. Un `.csv` che inizia con `#`
  (che finfo legge come sorgente C) è accettato.

`gif` e `svg` non sono più ammessi di serie: vanno dichiarati in
`extensions`. Le Resource Media `ImageResource` e `IconResource` accettano
`png`, `jpg`, `jpeg`, `webp`.

### SVG

Un `svg` ammesso da `extensions` passa da
`Wonder\App\Security\SvgSanitizer::sanitize()` prima di essere salvato:

- lettura solo DOM, con `LIBXML_NONET` e senza espandere entità;
- rifiutati (ALERT 931) `<!DOCTYPE>`, `<!ENTITY>`, XML non valido e radici
  non SVG (diverse da `<svg>` nel namespace `http://www.w3.org/2000/svg`);
- tolti gli elementi fuori dal namespace SVG (XHTML `form`, `input`, `meta`,
  XInclude, metadati di editor), `script`, `foreignObject`, `iframe`,
  `embed`, `object`, gli attributi `on*`, i valori con `javascript:` o
  `vbscript:`, i valori con un `data:` non raster (`png`, `jpeg`, `gif`,
  `webp`) in qualsiasi punto, gli stili con `@import`, `url(javascript` o
  `expression(` (anche scritti con escape CSS) e le processing instruction;
- un SVG senza nulla da togliere resta identico, byte per byte. Un SVG
  esportato da un editor con metadati propri (`sodipodi:`, `inkscape:`)
  perde quegli elementi.

Un modulo che salva SVG senza `uploadFiles()` chiama `sanitize()` e scarta
il file se restituisce `null`.

## Export CSV

`arrayToCsv()`, `sqlExport()` e il download delle Resource mettono un apice
davanti alle celle che iniziano con `=`, `+`, `-`, `@`, tabulazione o
ritorno a capo, così un foglio di calcolo non le esegue come formule
(`'=1+1`, `'@x`, `'+39 333 1234567`). Restano invariati i valori numerici
(`-5`, `+39`, `3.5`), i segni isolati (`-`) e ogni altra cella.

Opt-out, solo per CSV letti da programmi che non tollerano l'apice:

- `arrayToCsv($rows, $filename, false)`;
- `sqlExport($table, 'csv', false)`;
- `->downloadFormulaSafe(false)` sul `TableLayoutSchema`.

`downloadAsText()` resta com'era: apice davanti a ogni cella che inizia con
quei caratteri, numeri compresi (telefoni come `+393331234567`), e celle
di testo nell'export Excel. L'export Excel senza `downloadAsText()` non
cambia: le celle sono scritte con il loro tipo e mai come formula.

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

## Richieste che cambiano stato

- **Niente azioni in `GET`.** Logout backend e «Collega webhook» Stripe in
  `GET` mostrano una conferma; l'azione è un `POST` con `_csrf` nel corpo (o
  `X-WI-CSRF`). Un token nella query non conta. Vedi
  [Token CSRF](../concetti/form/csrf.md#pagine-backend-del-core).
- **Rinnovo del token di pagina** (`POST /api/token/page/`): same-origin,
  sessione attiva e token CSRF; il token resta legato alla sessione (`sid`).
  Vedi [API](api.md).
- **Header del proxy.** `X-Forwarded-For`, `-Proto`, `-Scheme` e `-Ssl` contano
  solo con `APP_TRUST_PROXY=true`, per cookie, IP del client (`ClientIp`) e
  URL assoluti (`UrlParser`). Vedi [Multi-ambiente](multi-ambiente.md).
- **Analitica.** Prima di GTM il layout frontend nega il consenso di default
  (Consent Mode) e passa al dataLayer solo un hash dell'id utente. Vedi
  [Google Tag Manager](../servizi/google-tag-manager/configurazione.md#consenso-e-datalayer).

## Log

`Logger` scrive in `ROOT/storage/logs/` e non usa mai la cartella corrente.
Per trovare la root prova, in ordine:

1. la costante `ROOT`;
2. `$GLOBALS['ROOT']`;
3. la prima cartella con `vendor/autoload.php`, risalendo dallo script in
   esecuzione.

Senza `ROOT` e senza uno script dentro il sito non cerca altrove (niente
fallback sulla cartella del pacchetto) e scrive con `error_log()`. Un nome
file con `..` viene rifiutato allo stesso modo.

Crea le cartelle con permessi `0750` e i file con `0640`. Se non riesce a
scrivere, ripiega su `error_log()` di PHP e non lancia eccezioni.

Con questi permessi l'utente di deploy (quello che lancia `php forge`) e
l'utente del web server (PHP-FPM) devono stare nello stesso gruppo, e la
cartella `storage/logs` deve appartenere a quel gruppo. Altrimenti, se Forge
crea `storage/logs` o un file di log, il web non riesce più a scriverci e i
log finiscono in silenzio in `error_log()`.
Sull'hosting reale: NON VERIFICATO.
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
