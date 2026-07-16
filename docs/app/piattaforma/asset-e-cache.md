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
  (rigenerati dal DB) sono versionati: dopo un deploy/update il filemtime
  cambia e la cache si invalida da sola.
- `Wonder\App\Dependencies::generate()` — tutte le librerie emesse
  (jquery, swiper, wi-lib, ...) sono versionate.

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
   `root.css`/`color.css` sul server con un nuovo filemtime → il `?v=`
   cambia e i browser scaricano la versione nuova.
