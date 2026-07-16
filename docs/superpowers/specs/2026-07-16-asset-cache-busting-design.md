# Cache busting per asset CSS/JS — Design

**Data:** 2026-07-16
**Stato:** approvato

## Problema

1. I `<link>`/`<script>` emessi dal framework puntano a URL senza parametro di
   versione. Lo scaffold `new-site` serve `.css`/`.js` con
   `Cache-Control: public, max-age=604800` (1 settimana): dopo una modifica il
   browser continua a servire la copia vecchia fino alla scadenza.
2. Sintomo osservato: dopo `forge export` + deploy, `root.css`/`color.css`
   sembravano non aggiornarsi. Verificato che la pipeline server-side è
   corretta (`deploy.yml` → FTP sync → `POST /api/app/update/` →
   `build/update/css.php` → `TableSync::importIfExists()` + `cssRoot()` +
   `cssColor()`): i file vengono rigenerati; era la cache browser a mostrare
   la versione vecchia.

## Soluzione

Versioning `?v={filemtime}` su ogni URL di asset locale emesso dal framework,
seguendo il precedente di `Wonder\App\Module\Assets` (moduli).

### 1. `Wonder\App\Support\Asset` (nuova classe)

- `Asset::version(string $url): string` — mappa un URL (prefissato `APP_URL`
  o path relativo `/...`) sul filesystem sotto `ROOT`; se il file esiste
  appende `?v={filemtime}`. Se l'URL ha già una query string, è esterno o il
  file non esiste, restituisce l'URL invariato. Mai eccezioni.
- `Asset::url(string $file): string` — costruisce e versiona l'URL di un file
  dentro `assets/{ASSETS_VERSION}/`.
  Es. `Asset::url('css/main.css')` → `{APP_URL}/assets/{v}/css/main.css?v=1721035200`.

### 2. Helper globali (`app/function/helper.php`)

```php
function __asset(string $file): string          // Asset::url()
function __asset_version(string $url): string   // Asset::version()
```

Wrapper sottili, stesso pattern di `module_asset()`.

### 3. Applicazione nel framework

- `app/view/components/frontend/layout/head.php` — i due `<link>` di
  `root.css`/`color.css` passano da `Asset::version()`. Poiché i file vengono
  rigenerati a ogni update/deploy, il filemtime cambia e la cache si invalida
  da sola.
- `Wonder\App\Dependencies::generate()` — ogni URL (jquery, swiper, wi-lib,
  ecc.) passa da `Asset::version()`.

### 4. Policy cache `.htaccess`

Con gli URL versionati la cache lunga è sicura:

- `Build::htaccessTemplate()` (framework, sorgente del template):
  `.js`/`.css` → `Cache-Control: public, max-age=31536000, immutable`,
  `ExpiresByType` css/js → 1 anno.
- `new-site/.htaccess` (scaffold, repo separato): stesso aggiornamento del
  file committato.

## Fuori scope

- Filename hashing con build step.
- Versioning degli upload (immagini): restano a `max-age=86400`.
- Refactor di `Module\Assets` per riusare `Asset::version()` (follow-up).

## Test

`tests/App/Support/AssetTest.php`, script standalone come gli altri test del
repo: URL con APP_URL, path relativo, file inesistente, query string già
presente, URL esterno, `Asset::url()` con e senza slash iniziale.
