# Asset e cache busting

Il framework versiona gli URL degli asset locali (css/js) con `?v={filemtime}`:
quando il file cambia, cambia l'URL e il browser scarica la copia nuova invece
di servire quella in cache. Questo permette all'`.htaccess` di tenere una
cache lunga (`max-age=31536000, immutable`) in sicurezza.

## `Wonder\App\Support\Asset`

| Metodo | Uso |
| --- | --- |
| `Asset::version(string $url): string` | Appende `?v={filemtime}` a un URL locale già costruito (prefisso `APP_URL` o path relativo `/...`). URL esterni, con query string/fragment già presenti o file inesistenti restano invariati. Mai eccezioni. |
| `Asset::url(string $file): string` | Costruisce e versiona l'URL di un file dentro `assets/{ASSETS_VERSION}/`. |

## Helper globali

```php
// File dentro assets/{ASSETS_VERSION}/
__asset('css/main.css');
// → https://sito.it/assets/1.0/css/main.css?v=1721035200

// URL locale arbitrario già costruito
__asset_version($PATH->css.'/set-up/root.css');
// → https://sito.it/assets/1.0/css/set-up/root.css?v=1721035200
```

Nei siti, usa `__asset()` per i css/js custom inclusi nei layout
(`custom/view/components/.../head.php`). Per gli asset dei moduli esiste
`module_asset($slug, $file)` (vedi [Sistema moduli](../concetti/moduli/sistema.md)),
che applica lo stesso schema `?v=`.

## Dove il framework lo applica

- `app/view/components/frontend/layout/head.php` — `root.css` e `color.css`
  (design token rigenerati dal DB) sono **inlinati** in `<style>` via
  `__inline_css()` per toglierli dal render-blocking; essendo dentro l'HTML
  dinamico si aggiornano da soli a ogni deploy/update, senza `?v=`. Se il file
  non è risolvibile, `__inline_css()` ricade sul `<link>` versionato.
- `Wonder\App\Dependencies::generate()` — tutte le librerie emesse
  (jquery, swiper, wi-lib, ...) sono versionate.

## Ridurre il render-blocking (LCP)

`Dependencies::generate()` supporta due flag opt-in per dipendenza, attivi **solo
sul frontend** (`$GLOBALS['FRONTEND']`), per togliere risorse dal percorso di
rendering iniziale:

- `'defer' => true` — emette `<script ... defer>`: il download non blocca il
  parsing e l'esecuzione resta in ordine. Sicuro **solo** per librerie non usate
  da `<script>` inline a parse-time (init via eventi o `DOMContentLoaded`/`load`).
  Attivo su `jquery-plugin`. **Non** deferibile ciò che è consumato inline (es.
  `jquery` per i `$()`, `wi-lib`/`wi-frontend` per `TranslationProvider.init`,
  `swiper` per i `new Swiper()` inline dei componenti).
- `'css_defer' => true` — carica il foglio di stile fuori dal render-blocking
  (`<link rel=preload as=style onload=...>` + fallback `<noscript>`). Sicuro
  **solo** per CSS non above-the-fold. Attivo su `bootstrap-icons`, `flag-icons`,
  `jquery-plugin`. Richiede che gli `onload` inline siano permessi (nessuna CSP
  stretta sugli handler inline).

Altre ottimizzazioni lato layout:

- **Design token inline**: `root.css` e `color.css` (variabili CSS, piccoli e
  alla base di tutta la cascata) sono inlinati in `<head>` con `__inline_css()`
  invece di essere `<link>` bloccanti — vedi sopra. L'helper usa
  `Asset::path()` (URL→file su disco, con le stesse guardie di `Asset::version()`)
  e ricade sul `<link>` versionato se il file non esiste. Riservato a CSS
  **piccoli e critici**: non inlinare `lib.css`/`head.css` (grandi) senza prima
  estrarre il critical CSS.
- **Font**: i Google Fonts (`css_font.link`) ricevono automaticamente
  `display=swap` in `head.php` se non già presente — testo subito visibile,
  niente FOIT che ritarda FCP/LCP. Font self-hosted o su altri CDN vanno gestiti
  nel loro `@font-face`.
- **Immagine LCP**: `Image::src(...)->priority()` marca l'immagine hero
  above-the-fold con `fetchpriority="high"` e `loading="eager"`, così il browser
  la scarica tra le prime risorse. Usare su **una sola** immagine per pagina; il
  resto delle immagini dovrebbe restare `loading="lazy"`.

## Policy cache `.htaccess`

Il template generato da `php forge build` (`Build::htaccessTemplate()`) serve
`.css`/`.js` con:

```apache
Header set Cache-Control "public, max-age=31536000, immutable"
```

La cache lunga è sicura solo perché gli URL sono versionati. Se un sito emette
css/js **senza** passare da `__asset()` / `__asset_version()` /
`Dependencies`, quei file restano in cache fino a un anno: versiona sempre.

## root.css / color.css e il deploy

Il flusso che mantiene allineati i CSS generati dal DB:

1. modifiche allo stile dal backend (o import) aggiornano il DB locale;
2. `forge export` scrive `shared/sync-data.json` (committato in git);
3. al deploy, la GitHub Action chiama `POST /api/app/update/`;
4. `build/update/css.php` esegue `TableSync::importIfExists()` e rigenera
   `root.css`/`color.css` sul server. Essendo inlinati in `<head>` via
   `__inline_css()`, la richiesta successiva serve già il CSS aggiornato (nessun
   `?v=` da invalidare per questi due file).
