---
icon: language
---

# URL multilingua

URL localizzati per slug, default italiano. Il sito serve `/contatti/` come URL canonical della pagina contatti per la lingua italiana e `/contact/` per la lingua inglese, mantenendo `__r('contact')` e `__u('contact')` coerenti senza dover scrivere due route diverse.

{% hint style="info" %}
Stato: **on dalla v2.1.x**. La feature è opt-in: senza configurazione il sito si comporta esattamente come prima. Una sola route per pagina, traduzione gestita via JSON.
{% endhint %}

## Idea di base

Una sola route definisce il path **canonical** (in inglese, per convenzione). Un file JSON per lingua dichiara la traduzione. Al caricamento il framework espande automaticamente la route in N varianti, una per locale, tutte con lo stesso handler.

Risultato:

- il browser visita `/contatti/` → la stessa logica server di `/contact/`, ma la lingua corrente diventa `it`;
- `__r('contact')` con lingua `it` → `/contatti/`;
- `__u('products/scarpe-rosse')` con lingua `it` → `https://site/prodotti/scarpe-rosse/`;
- `<link rel="alternate" hreflang="...">` automatico via `LanguageContext::renderHead()`.

Una sola URL canonical per lingua. Un solo handler. Un solo JSON.

## File da conoscere

### Core

- `class/Localization/UrlTranslator.php` — caricatore JSON e API forward / reverse / instance.
- `class/Localization/LanguageContext.php` — registro lingue, strategia `langSource`, `createLangUrl()` e `switchLangUrl()`.
- `class/Http/Route.php` — `expandTranslatableRoutes()`, `url()` con consapevolezza lingua.
- `class/Http/RouteDispatcher.php` — `bootLanguages()`, set lingua post-match, 301 canonical → tradotta.
- `class/Http/RouteDefinition.php` — `->translatable(bool)` per opt-in / opt-out per route.
- `class/Http/RouteGroup.php` — `->translatable(bool)` per opt-in / opt-out per gruppo.

### File del consumer (progetto / `new-site`)

- `custom/config/lang.php` — registra lingue, paths, modalità.
- `custom/config/routes/route.frontend.php` — route canonical (sintassi standard).
- `lang/{locale}/urls.json` — traduzione path per ciascuna lingua.

## Convenzione `urls.json`

```json
{
  "contact": "contatti",
  "legal/privacy-policy": "legali/privacy",
  "products/{slug}": "prodotti/{slug}",
  "blog/{year}/{slug}": "diario/{year}/{slug}"
}
```

Regole:

- **Chiave** = path canonical senza slash iniziale e finale; i parametri restano `{nome}` letterali.
- **Valore** = path tradotto con gli stessi parametri (gli stessi nomi, lo stesso numero).
- I parametri vengono propagati al runtime: `products/scarpe-rosse` → `prodotti/scarpe-rosse`.
- Il file della lingua default può anche mancare o essere vuoto.
- Più directory `lang/` sono cumulabili (TASK D moduli plugin): registrate con `LanguageContext::addUrlsPath()`, l'ultima registrata vince in caso di chiavi duplicate.

In ambiente non-production (`APP_DEBUG ≠ false`) vengono loggate via `error_log()`:

- traduzioni mancanti per chiavi richieste a runtime;
- placeholder mismatch fra chiave e valore (es. `products/{slug}` → `prodotti/{altro}`);
- collisioni reverse (due chiavi diverse che mappano allo stesso path tradotto);
- JSON malformato.

In production restano silenziati e si ricade sul canonical.

## Come gira davvero

Pseudocodice del flusso di una request `GET /contatti/`:

```text
1. handler/index.php → RouteDispatcher::handleRequest()
2. bootLanguages()                          → require_once custom/config/lang.php
                                              (registra IT/EN, addUrlsPath, langSource='translation')
3. Route::loadDirectories(...)              → registra route canonical
4. Route::expandTranslatableRoutes(...)     → per ogni route translatable
                                              + ogni lingua con traduzione,
                                              aggiunge variante con _locale
5. Router::match('GET', '/contatti/')       → matcha la variante IT
6. dispatcher: route['_locale'] = 'it'      → LanguageContext::setLang('it')
                                              setLangSource('translation')
7. bootApplication() → wonder-image.php     → service/lang.php; setLang non
                                              sovrascrive perché l'URL ha vinto
8. maybeRedirectToTranslated($route)        → no-op (è già una variante)
9. include $route['handler']                → render in IT
```

Per `GET /contact/` con utente che ha lingua `it`:

```text
1-5. ... la canonical /contact/ matcha
6. dispatcher: route['_locale'] vuoto       → niente set lingua
7. bootApplication() → service/lang.php     → setLang('it') (preferenza utente)
8. maybeRedirectToTranslated($route)        → langSource='translation' + _canonical_path
                                              presente + traduzione IT esiste
                                            → 301 to /contatti/, query string preservata
```

## Setup in un progetto

### Step 1: dichiarare lingue + paths

File `custom/config/lang.php`:

```php
<?php

    use Wonder\Localization\LanguageContext;

    LanguageContext::addLangPath($ROOT.'/lang/');
    LanguageContext::addUrlsPath($ROOT.'/lang/');

    LanguageContext::addLanguage('it', 'Italiano', '/', 'it', ['IT']);
    LanguageContext::addLanguage('en', 'English',  '/', 'gb', ['GB', 'US']);

    LanguageContext::setLangSource('translation');
    LanguageContext::setLang('it'); // default
```

`bootLanguages()` del dispatcher carica questo file PRIMA del routing.

### Step 2: aggiungere il file di traduzioni

File `lang/it/urls.json`:

```json
{
  "contact": "contatti"
}
```

File `lang/en/urls.json` (opzionale, può anche essere `{}`):

```json
{}
```

### Step 3: registrare la route canonical

File `custom/config/routes/route.frontend.php`:

```php
<?php

    use Wonder\Http\Route;

    Route::area('frontend')->group(function () use ($ROOT) {

        Route::get('/contact/', $ROOT.'/custom/pages/contact.php')
            ->name('contact');

    });
```

A questo punto:

- `https://site/contact/` con utente IT → 301 a `/contatti/`;
- `https://site/contatti/` → render OK in IT;
- `__r('contact')` con lingua IT → `/contatti/`;
- `__u('contact')` con lingua IT → `https://site/contatti/`.

## Come aggiungere una traduzione nuova

Supponiamo di voler tradurre `/products/{slug}/` come `/prodotti/{slug}/`.

### Step 1: aggiungere la traduzione nel JSON

In `lang/it/urls.json`:

```json
{
  "products/{slug}": "prodotti/{slug}"
}
```

### Step 2: la route esiste già

Niente da fare lato route: la canonical è già registrata in `route.frontend.php`. Al prossimo bootstrap il framework espande automaticamente la variante IT.

### Step 3: usare `__r()` o `__u()` nei template

```php
<a href="<?=__r('products', ['slug' => $product->slug])?>">
    <?=$product->name?>
</a>
```

Con lingua IT renderizza `/prodotti/scarpe-rosse/`. Con lingua EN renderizza `/products/scarpe-rosse/`.

## Opt-in / opt-out per singola route

Default: `area('frontend')` traducibile, `area('api')` e `area('backend')` no.

### Forzare opt-out su una route frontend

```php
Route::get('/internal-tool/', $ROOT.'/custom/pages/tool.php')
    ->name('tool')
    ->translatable(false);
```

La route non viene espansa, qualunque traduzione esista in `urls.json` per la chiave `internal-tool` viene ignorata.

### Forzare opt-in per un gruppo intero

```php
Route::area('frontend')
    ->translatable(true) // ridondante, già default
    ->group(function () use ($ROOT) {
        // ...
    });
```

### Forzare opt-out per tutto un gruppo

```php
Route::area('frontend')
    ->translatable(false)
    ->group(function () use ($ROOT) {
        Route::get('/admin-preview/', $ROOT.'/custom/pages/admin-preview.php')
            ->name('admin-preview');
    });
```

## Switch lingua dal selettore

`LanguageContext::switchLangUrl($currentUrl, $targetLang)` con `langSource='translation'` riconosce la traduzione attuale, risale al canonical, e ricostruisce l'URL nella lingua target.

```php
<a href="<?=__su($SEO->url, 'en')?>">English</a>
<a href="<?=__su($SEO->url, 'it')?>">Italiano</a>
```

Esempio:

- pagina corrente: `https://site/prodotti/scarpe-rosse/`
- click su "English" → `https://site/products/scarpe-rosse/`

## Plugin / moduli (TASK D)

Un modulo plugin che porta nuove pagine (es. `wonder-image/blog`) può registrare la sua directory di traduzioni:

```php
LanguageContext::addUrlsPath($packageRoot.'/lang/');
```

Convenzione filesystem:

```text
vendor/wonder-image/blog/lang/it/urls.json
vendor/wonder-image/blog/lang/en/urls.json
```

In caso di chiavi duplicate vince **l'ultimo path registrato**, quindi il progetto consumer può sovrascrivere le traduzioni del modulo registrando il suo `lang/` per ultimo.

## Routing inverso

| Helper | Cosa restituisce in lingua IT (con `urls.json`) |
|---|---|
| `__r('contact')` | `/contatti/` |
| `__r('products', ['slug' => 'foo'])` | `/prodotti/foo/` |
| `__u('contact')` | `https://site/contatti/` |
| `__u('products/foo')` | `https://site/prodotti/foo/` |
| `Route::url('contact')` | `/contatti/` |

In lingua EN (senza traduzione in `lang/en/urls.json`) tutti restituiscono il canonical `/contact/`, `/products/foo/`, etc. Nessun crash, nessun 500, fallback silenzioso.

## Strict canonical e SEO

La policy attuale è "una URL canonical per lingua":

- canonical IT = `/contatti/` (lingua IT)
- canonical EN = `/contact/` (lingua EN, via `urls.json` vuoto)

Quindi un utente IT che arriva su `/contact/` riceve un 301 a `/contatti/`. Questo evita contenuti duplicati su Google e risolve il problema dei `<link rel="canonical">` divergenti per lingua.

Il redirect avviene **solo** quando:

- `langSource === 'translation'` (consumer in modalità tradotta);
- la route matched è la canonical, non una variante;
- esiste una traduzione del path canonical per la lingua corrente;
- la traduzione differisce dal canonical.

## Errori comuni

### 1. Modifica al `urls.json` non ha effetto

Cause tipiche:

- il file è in una directory non registrata via `addUrlsPath()`;
- chiave o valore con slash iniziale/finale (`/contact/` invece di `contact`) — la normalizzazione li toglie ma evita ambiguità scrivendoli senza;
- placeholder rinominati (`products/{slug}` → `prodotti/{prodotto}`): incompatibili. Mantieni gli stessi nomi.

Verifica:

```php
\Wonder\Localization\UrlTranslator::has('contact', 'it');   // bool
\Wonder\Localization\UrlTranslator::all('it');              // array completo
```

### 2. Il browser non viene redirectato da `/contact/` a `/contatti/`

Cause tipiche:

- `setLangSource('translation')` non chiamato in `custom/config/lang.php`;
- la route ha `->translatable(false)` o `area('api')` / `area('backend')`;
- la lingua corrente al momento del 301 è la lingua per cui non esiste traduzione (es. EN con `urls.json` vuoto).

### 3. `__r('contact')` ritorna ancora `/contact/`

Cause tipiche:

- la route non ha `name('contact')`;
- la lingua corrente è quella senza traduzione;
- `langSource` non è `'translation'`;
- `expandTranslatableRoutes()` non viene chiamato perché `bootLanguages()` non vede `custom/config/lang.php` (file mancante o path errato).

### 4. La variante tradotta non matcha

Sintomo: `https://site/contatti/` ritorna 404.

Cause tipiche:

- nessuna lingua registrata via `addLanguage()` al momento di `expandTranslatableRoutes()` (in `custom/config/lang.php` chiama PRIMA `addLanguage()` di tutto il resto);
- la chiave in `urls.json` non corrisponde al path canonical normalizzato (deve essere `contact`, non `/contact/` né `Contact`);
- placeholder mismatch.

### 5. Conflitto con strategia `langSource = 'path'` legacy

`langSource = 'path'` (prefisso `/it/...` nell'URL) e `langSource = 'translation'` sono **mutuamente esclusivi**. Scegli una sola strategia per progetto. La strategia `translation` è quella consigliata per nuovi progetti.

### 6. Modulo plugin override-ato dal progetto

Comportamento intenzionale: l'ultimo `addUrlsPath()` registrato vince, così il progetto consumer ha l'ultima parola. Per debuggare:

```php
\Wonder\Localization\UrlTranslator::paths(); // array, ordine di registrazione
```

## Best practice

- Tieni il path canonical in inglese, sempre. È solo una "chiave", non la versione inglese del sito.
- Tieni la chiave del JSON identica al path canonical senza gli slash. Evita ambiguità.
- Mai inserire path API o backend in `urls.json`. Quei path sono cross-lingua.
- Mai rinominare i parametri nella traduzione (`{slug}` resta `{slug}`).
- `addUrlsPath()` viene chiamato in `custom/config/lang.php` PRIMA delle route. Il dispatcher carica `lang.php` PRIMA del routing.
- Quando aggiungi una traduzione nuova, ricordati di aggiungere la stessa chiave **a tutti i `urls.json` delle lingue gestite**, anche se vuota. Evita drift fra lingue.
- Per il selettore lingua, usa sempre `__su($currentUrl, $targetLang)` invece di concatenare path a mano.
- Se distribuisci un modulo plugin, esponi i path delle traduzioni dal `vendor/wonder-image/<modulo>/lang/` e lascia che il progetto consumer registri il suo `lang/` per ultimo per le override.
- Tieni la lingua di default coerente fra `setLang()` e `defaultLang` di `LanguageContext`.
- In production controlla che `APP_DEBUG=false`: i warning di traduzione mancante sono solo per dev.

## Formula pratica da ricordare

Per aggiungere una pagina nuova con URL tradotto:

1. Definisci la route canonical (in inglese) con `Route::get(...)->name(...)`.
2. Aggiungi la chiave canonical in `lang/{locale}/urls.json` per ciascuna lingua diversa dalla default.
3. Nei template usa `__r('nome-route', $params)` o `__u('canonical-path/'.$value)` per generare i link.
4. Niente da configurare nel router. Il framework espande, matcha, redirige.
