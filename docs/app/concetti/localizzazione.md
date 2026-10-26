# Localizzazione

## Ordine di bootstrap della lingua

La lingua di una richiesta attraversa tre fasi, in quest'ordine:

1. **Pre-routing** — `RouteDispatcher::bootLanguages()` carica
   `custom/config/lang.php` del consumer, che registra lingue
   (`LanguageContext::addLanguage`), path traduzioni (`addLangPath` /
   `addUrlsPath`) e sceglie la strategia (`setLangFromPath()`,
   `setLangFromHeader()`, ecc.). Serve prima del routing perché
   `Route::expandTranslatableRoutes()` deve conoscere le lingue.
2. **Config** — `TranslationBootstrap::preload()` (da `config.php`)
   aggiunge i path di framework, consumer e moduli e inizializza il
   `TranslationProvider` in modo best-effort.
3. **Servizi** — `app/service/lang.php` registra il path traduzioni del
   framework e, **solo se il consumer non ha già registrato lingue**,
   applica il default (`it`). Poi re-inizializza il `TranslationProvider`
   e imposta i global replacements (`{{path_privacy_policy}}`, ecc.).

Regola di sicurezza: `LanguageContext::defaultLang()` non sovrascrive mai
una lingua già scelta da una strategia (`langSource !== 'none'`). I
richiami ripetuti durante il bootstrap sono quindi innocui.

## Strategie

- **path** (`setLangFromPath`) — lingua nel primo segmento URL
  (`/it/...`, `/en/...`). Le route del consumer usano `prefix('/{lang}')`
  con `translatable(false)`. `Route::resolvePath()` riempie un `{lang}`
  non passato dai caller con la lingua corrente, quindi `__r(...)` è
  utilizzabile normalmente.
- **translation** (`urls.json`) — path tradotti per lingua, senza
  prefisso; le varianti vengono espanse da `expandTranslatableRoutes()` e
  il dispatcher gestisce lingua e 301 canonical → variante.
- **subdomain / domain / query / header** — vedi i rispettivi
  `setLangFromX()` su `LanguageContext`.

## Contenuti in un'altra lingua

Un contenuto che ha una lingua propria (per esempio il PDF di un documento
legale `de` generato da una richiesta italiana) usa
`LanguageContext::withLang($codice, $callback)`: imposta la lingua, ricarica
le traduzioni, esegue il callback (che riceve la lingua effettiva) e
ripristina lingua e traduzioni correnti in un `finally`, anche se il callback
lancia. Un codice vuoto o non registrato con `addLanguage()` ricade sulla
lingua di default; se la lingua coincide con quella corrente non ricarica
nulla. Risolvi dentro il callback solo stringhe: ciò che legge `__t()` più
tardi (per esempio `Footer()` di FPDF dentro `Output()`) vede di nuovo la
lingua corrente.

```php
$labels = LanguageContext::withLang($document->language_code, static fn (): array => [
    'page' => __t('components.legal.pdf.page'),
]);
```

## Redirect della home senza lingua

Per i siti in modalità path la home `/` non esprime una lingua. La
convenzione è il redirect alla home localizzata con
`LanguageRedirector::redirectByCountry()`:

- paese del visitatore (di default `Geo::country()`, vedi sotto; ai bot non
  serve e non si calcola) confrontato con i
  `countries` dichiarati in `addLanguage(...)`;
- fallback su `Accept-Language`;
- fallback sulla lingua default. I bot ricevono sempre la lingua default.

Il redirect è `302` + `Vary: Accept-Language` (un 301 verrebbe cachato dal
browser per sempre). Il framework espone l'handler pronto
`app/http/frontend/lang-redirect.php`; dal consumer:

```php
Route::area('frontend')
    ->response('html')
    ->translatable(false)
    ->group(function () use ($ROOT_APP) {

        Route::get('/', $ROOT_APP.'/http/frontend/lang-redirect.php')
            ->name('lang-redirect');

    });
```

## Paese del visitatore

`Wonder\Plugin\GeoPlugin\Geo::country()` restituisce il codice ISO a due
lettere del visitatore (`IT` se non si trova). Il calcolo è pigro: alla prima
chiamata `IPInfo` interroga i provider (ipinfo.io, poi ip-api.com, poi
ipwho.is, 3 s + 1 s di connessione ciascuno) e il codice finisce in
`$_SESSION['system_cache']['country']`; le chiamate successive lo rileggono da
lì. La sessione nuova non fa più chiamate: `config/app/session.php` prepara solo
`system_cache` e `system_cache.geo`.

Con IP privati, locali o riservati (`127.0.0.1`, `::1`, `10.x`, `192.168.x`...)
nessun provider viene chiamato e resta `IT`.

```php
use Wonder\Plugin\GeoPlugin\Geo;

$country = Geo::country();
```

Non leggere `$_SESSION['system_cache']['country']` direttamente: prima della
prima chiamata all'accessor la chiave non c'è.
