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

## Redirect della home senza lingua

Per i siti in modalità path la home `/` non esprime una lingua. La
convenzione è il redirect alla home localizzata con
`LanguageRedirector::redirectByCountry()`:

- paese del visitatore (di default `$_SESSION['system_cache']['country']`,
  popolato da `config/app/session.php` via GeoPlugin) confrontato con i
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
