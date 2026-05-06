---
icon: layer-group
---

# Freecode (componenti custom)

Questa pagina descrive la struttura `custom/` pensata per il metodo "freecode": il grafico
modifica solo layout/stile, il programmatore modifica solo logica/dati, l'AI capisce subito
dove intervenire senza leggere tutto il progetto.

{% hint style="info" %}
Stato: **on dalla fase 2b**. Header, body-start e footer del progetto delegano ai componenti
descritti qui; `demo.php` usa la factory `renderPage()` come pilota. Le pagine legacy che
ancora aprono `<!DOCTYPE html>` a mano restano valide finché non vengono migrate, ma niente
di nuovo dovrebbe essere scritto in stile legacy.
{% endhint %}

## Idea di base

Ogni file deve avere una sola responsabilità. Quattro categorie nette:

| Cartella | Cosa contiene | Cosa NON può fare |
|---|---|---|
| `custom/components/layout/` | pezzi visuali fissi (header, footer, legal bar) | query SQL, logica condizionale complessa |
| `custom/components/sections/` | sezioni riusabili (hero, contact form, CTA) | logica, dati, side effect |
| `custom/components/ui/` | atomici (card, badge, button group) | logica, dati |
| `custom/components/functional/` | componenti con logica/dati (popup, cookie banner) | layout HTML hardcoded |

Risultato: il grafico tocca `layout/` + `sections/` + `ui/`, il programmatore tocca `functional/`,
e i due lavori non si scontrano mai.

## File da conoscere

### Factory pagine

- `custom/lib/page.php` — funzione `renderPage(array $config)`

### Componenti

- `custom/components/layout/site-footer.php`
- `custom/components/layout/legal-bar.php`
- `custom/components/sections/contact-form.php`
- `custom/components/functional/popup.php`
- `custom/components/README.md` — riferimento rapido sulle 4 cartelle

### Configurazione

- `custom/config/navigation.php` — array delle voci di nav (sorgente unica)

### Esempio pagina

- `custom/pages/_example.php` — esempio minimo di pagina che usa la factory `renderPage()`
- `demo.php` — pilota di migrazione: home in 30 righe usando `renderPage()`

## Come gira la factory `renderPage()`

Una pagina del sito oggi (legacy) duplica circa 30 righe di boilerplate: `$FRONTEND`, `$ROOT`,
require di `wonder-image.php`, set di `$SEO`, scaffold HTML, include di head/header/footer/body-end.

Con la factory diventa così:

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/custom/lib/page.php';

renderPage([
    'key'    => 'home',
    'render' => function () {
        ?>
        <section class="intro">
            <div class="content">
                <h1 class="title-big"><?=__t('pages.home.content.hero.title')?></h1>
            </div>
        </section>
        <?php
    },
]);
```

La factory si occupa di tutto il boilerplate: bootstrap del framework, set di `$SEO` da
`lang/{locale}/pages.json` (chiave `pages.{key}.seo.*`), scaffold `<!DOCTYPE html>...</html>`,
include di head/body-start/header/footer/body-end.

### Argomenti di `renderPage`

| Chiave | Tipo | Default | Note |
|---|---|---|---|
| `key` | string | `'home'` | Usata per leggere SEO da `lang/{locale}/pages.json` (`pages.{key}.seo.*`) e label nav (`components.navigation.{key}`). |
| `frontend` | bool | `true` | Equivale a `$FRONTEND` legacy. |
| `private` | bool | `false` | Equivale a `$PRIVATE` legacy. |
| `permit` | array | `[]` | Equivale a `$PERMIT` legacy. |
| `url_path` | string | `''` | Path passato a `__u()`. |
| `seo` | array | (da i18n) | Override SEO: `['title' => ..., 'description' => ..., 'url' => ...]`. |
| `breadcrumb` | array | auto | Default: `[$SEO->url => __t("components.navigation.{key}")]`. |
| `render` | callable | nessuno | Closure che stampa il contenuto della pagina. |

## Come usare i componenti

Tre forme di chiamata, una per categoria.

### Include diretto (`layout/`)

```php
<?php include $ROOT.'/custom/components/layout/site-footer.php'; ?>
```

Il componente legge da scope (`$SOCIETY`, `$SEO`, ...). Niente da passare.

### Include con argomenti (`sections/`)

```php
<?php
$args = [
    'title'   => 'Scrivici',
    'showMap' => false,
];
include $ROOT.'/custom/components/sections/contact-form.php';
?>
```

Le sezioni leggono `$args` (con default in cima al file). Riusabili da pagine diverse.

### Funzione (`functional/`)

```php
<?php
require_once $ROOT.'/custom/components/functional/popup.php';
renderPopup($PAGE_KEY);
?>
```

I componenti `functional/` espongono **funzioni PHP** invece di HTML inline, così la logica
può essere riusata, testata e modificata senza toccare il layout.

## Navigazione: una sola sorgente di verità

`header.php` itera su `custom/config/navigation.php` per produrre sia il nav desktop sia il nav mobile. Niente label hardcoded, niente duplicazione.

Forma minima:

```php
return [
    [ 'key' => 'home',    'url' => '' ],
    [ 'key' => 'contact', 'url' => 'contact' ],
];
```

Aggiungere una voce = aggiungere una riga. Le label vengono lette da `lang/{locale}/components.json` con chiave `components.navigation.{key}`.

### Subnav (children)

Una voce può avere una sotto-lista statica:

```php
return [
    [ 'key' => 'home', 'url' => '' ],
    [
        'key'      => 'services',
        'url'      => 'services',
        'children' => [
            [ 'key' => 'service_a', 'url' => 'services/a' ],
            [ 'key' => 'service_b', 'url' => 'services/b' ],
        ],
    ],
    [ 'key' => 'contact', 'url' => 'contact' ],
];
```

Il rendering è ricorsivo: ogni `child` può a sua volta avere `children`. Il componente `custom/components/ui/nav-item.php` gestisce sia il caso senza figli (delega a `nav-link.php`) sia il caso con figli (produce `<div class="nav-has-children">` con link parent + `<div class="nav-children">`).

Il designer applica lo stile via le classi `.nav-has-children` e `.nav-children` (dropdown desktop, accordion mobile, ecc.) — la struttura PHP non impone niente.

### Voci esterne

```php
[ 'key' => 'docs', 'url' => 'https://docs.example.com', 'external' => true ],
```

Quando `external` è `true`, l'href è usato as-is (no `__u()`) e l'`<a>` riceve `target="_blank" rel="noopener noreferrer"`.

## Come aggiungere una nuova pagina

### Step 1: aggiungi le label i18n

In `lang/it/pages.json`:

```json
"about" : {
    "seo" : {
        "title" : "Chi siamo - {{society_name}}",
        "description" : "..."
    },
    "content" : {
        "hero" : { "title" : "Chi siamo" }
    }
}
```

In `lang/it/components.json` (sezione `navigation`):

```json
"about" : "Chi siamo"
```

### Step 2: aggiungi la voce di nav

In `custom/config/navigation.php`:

```php
[ 'key' => 'about', 'url' => 'about' ],
```

### Step 3: crea la pagina

File `custom/pages/about.php`:

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/custom/lib/page.php';

renderPage([
    'key'    => 'about',
    'render' => function () {
        ?>
        <section class="intro">
            <div class="content">
                <h1 class="title"><?=__t('pages.about.content.hero.title')?></h1>
            </div>
        </section>
        <?php
    },
]);
```

### Step 4: registra la route

In `custom/config/routes/route.frontend.php` (creando il file se non esiste, vedi
[Routing](routing.md)):

```php
Route::get('/about/', $ROOT.'/custom/pages/about.php')->name('about');
```

## Come aggiungere una nuova sezione riusabile

### Step 1: crea il file in `sections/`

File `custom/components/sections/cta.php`:

```php
<?php
    $args     = $args ?? [];
    $title    = $args['title']    ?? 'Iniziamo?';
    $cta      = $args['cta']      ?? 'Contattaci';
    $cta_url  = $args['cta_url']  ?? __u('contact');
?>
<section class="bg-primary">
    <div class="content content-medium a-c">
        <h2 class="title tx-white"><?=$title?></h2>
        <a href="<?=$cta_url?>" class="btn btn-secondary mt-5"><?=$cta?></a>
    </div>
</section>
```

### Step 2: usala da una pagina

```php
renderPage([
    'key'    => 'home',
    'render' => function () {
        ?>
        <section class="intro"><!-- hero --></section>
        <?php
        $args = ['title' => 'Pronti a partire?'];
        include $_SERVER['DOCUMENT_ROOT'].'/custom/components/sections/cta.php';
    },
]);
```

## Come aggiungere un componente functional

I componenti `functional/` espongono funzioni PHP. Niente HTML hardcoded nel file: se serve
markup, fai `include` di un componente `sections/` o `ui/`.

File `custom/components/functional/cookie-banner.php`:

```php
<?php
    function renderCookieBanner(): void
    {
        if (!empty($_COOKIE['cookie_consent'])) {
            return;
        }

        // logica...
        // poi rendering via componente UI:
        $args = [
            'message' => __t('components.cookie.message'),
        ];
        include __DIR__.'/../ui/banner.php';
    }
```

## Dove intervenire, file per file

### Voglio cambiare il layout del footer

- `custom/components/layout/site-footer.php` (layout)
- `custom/components/layout/legal-bar.php` (link legali)

### Voglio cambiare il form contatto

- `custom/components/sections/contact-form.php` (markup form)
- `api/frontend/form/index.php` (handler invio)
- `lang/{locale}/components.json` (label dei campi)

### Voglio aggiungere una voce di menu

- `custom/config/navigation.php`
- `lang/{locale}/components.json` (label sotto `navigation.`)

### Voglio cambiare la logica del popup

- `custom/components/functional/popup.php` (funzione `renderPopup`)
- (NON toccare `custom/utility/frontend/body-start.php` finché 2b non lo cabla)

### Voglio creare una pagina nuova

Vedi sezione "Come aggiungere una nuova pagina" sopra.

## Errori comuni

### 1. Layout e logica nello stesso file

Sintomo: il file `custom/components/layout/qualcosa.php` contiene una `sqlSelect` o un blocco
`if ($_SESSION[...])`.

Causa: scelta sbagliata di cartella.

Fix: spostalo in `custom/components/functional/qualcosa.php` ed espone una funzione che la
pagina chiama.

### 2. Sezione con dati hardcoded

Sintomo: una sezione `sections/X.php` ha testi italiani direttamente nel markup.

Causa: bypass dell'i18n.

Fix: sposta i testi in `lang/{locale}/components.json` o `pages.json` e leggili con `__t()`.

### 3. Pagina che duplica boilerplate

Sintomo: una pagina nuova copia 30 righe di scaffold (`<!DOCTYPE html>`, include head/body-start/header/footer/body-end) invece di usare `renderPage()`.

Causa: distrazione, oppure copia-incolla da una pagina legacy non ancora migrata.

Fix: usa `renderPage()` come da `custom/pages/_example.php` o `demo.php`. La factory si occupa del bootstrap, dello scaffold HTML e degli include — la pagina definisce solo `key` + closure `render`.

### 4. Componente functional che restituisce HTML invece di stamparlo

Sintomo: `renderPopup()` ritorna una stringa, e la pagina chiama `echo renderPopup(...)`.

Causa: incoerenza con il resto dei componenti che fanno `echo`/`print` direttamente.

Fix: il componente stampa lui stesso (`echo`/`include`) e la firma è `void`. Se serve la
stringa, è una funzione diversa (es. `popupHtml()` come builder).

## Best practice

- Una sola responsabilità per file: layout o logica, mai entrambi.
- Sezioni riusabili leggono argomenti da `$args` con default in cima al file.
- I componenti `functional/` espongono funzioni; le funzioni stampano, non ritornano HTML.
- Tutta la nav del sito vive in `custom/config/navigation.php`.
- Tutte le pagine usano `renderPage()`. Niente boilerplate ripetuto.
- Tutto ciò che è testo per l'utente sta in `lang/{locale}/{pages,components,emails}.json`.
- Tutto ciò che è schema tabella DB sta in `custom/build/table/{nome-tabella}.php` (un file per tabella).
- Se devi creare un componente nuovo, scegli la cartella **prima** di scriverlo. Se non riesci a decidere fra `layout/` e `sections/`, probabilmente è una sezione.

## Formula pratica da ricordare

Per modificare il sito senza rompere nulla:

1. Identifica **chi** sta chiedendo: grafico o programmatore.
2. Apri **una sola** delle quattro cartelle in base alla richiesta.
3. Tocca **un solo file**.
4. Se la modifica obbliga a toccare due cartelle diverse, ripensa al confine: probabilmente
   ti manca un'astrazione (es. argomento `$args` mancante, oppure label da spostare in i18n).
