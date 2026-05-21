---
icon: layer-group
---

# Freecode (override view e componenti custom)

Questa pagina descrive il flusso corretto per lavorare in modalita' "freecode" oggi.

Nel core `wonder-image/app` il rendering usa:

- `Wonder\View\View`
- layout in `app/view/layout/...`
- componenti in `app/view/components/...`
- route frontend in `app/config/routes/route.frontend.php`
- logica dati in `class/App/...`

Nel progetto host puoi fare override senza toccare il package usando:

- `custom/view/layout/...`
- `custom/view/components/...`

{% hint style="info" %}
Questo package e' una libreria. I path `custom/...` non vivono qui dentro: esistono nel progetto
consumer, ad esempio `new-site`. Nel core devi aggiungere o correggere componenti e classi sotto
`app/view/...` e `class/App/...`; nel progetto host devi fare gli override in `custom/view/...`.
{% endhint %}

## Idea di base

Il principio resta semplice:

1. il backend o il model prepara i dati
2. il componente view li renderizza
3. il progetto host puo' sostituire layout e componenti senza forkare il core

Quindi:

- niente query SQL nuove dentro i layout
- niente logica applicativa pesante dentro i componenti visuali
- niente nuovo uso di `custom/utility/*`
- niente nuova documentazione che parli di `custom/components/...` o `custom/lib/page.php`, perche' non e' piu' il flusso reale

## Struttura reale

### Core package

- `class/View/View.php`
- `app/view/layout/frontend/base.php`
- `app/view/layout/frontend/main.php`
- `app/view/layout/backend/base.php`
- `app/view/layout/backend/main.php`
- `app/view/components/frontend/layout/head.php`
- `app/view/components/frontend/layout/body-start.php`
- `app/view/components/frontend/layout/body-end.php`
- `app/view/components/frontend/layout/header.php`
- `app/view/components/frontend/layout/footer.php`
- `app/view/components/frontend/overlay/popup.php`
- `app/view/components/frontend/overlay/annuncement.php`
- `class/App/Models/Communications/Popup.php`
- `class/App/Models/Communications/Announcement.php`
- `class/App/Resources/Communications/PopupResource.php`

### Override nel progetto host

- `custom/view/layout/frontend/...`
- `custom/view/layout/backend/...`
- `custom/view/components/frontend/...`
- `custom/view/components/backend/...`

La risoluzione degli override passa da `View::component()` e `View::layout()`.
Il progetto host ha priorita' sul core.

## Come vengono risolti layout e componenti

### Componenti

Quando chiami:

```php
<?= \Wonder\View\View::component('frontend.layout.head') ?>
```

il resolver cerca in quest'ordine:

1. `custom/view/components/frontend/layout/head.php`
2. `app/view/components/frontend/layout/head.php`

Lo stesso vale per componenti come:

```php
<?= \Wonder\View\View::component('overlay.popup') ?>
<?= \Wonder\View\View::component('ui.button', ['label' => 'Apri']) ?>
```

Nota: se il nome non parte da `frontend.` o `backend.`, viene considerato frontend.
Per esempio `ui.button` diventa `frontend/ui/button.php`.

### Layout

Quando una view pagina apre:

```php
<?php \Wonder\View\View::layout('frontend.main'); ?>
```

il resolver cerca in quest'ordine:

1. `custom/view/layout/frontend/main.php`
2. `custom/view/layout/frontend/main_layout.php`
3. `app/view/layout/frontend/main.php`
4. `app/view/layout/frontend/main_layout.php`

## Flusso corretto

### 1. Route

Le route frontend del core passano da:

- `app/config/routes/route.frontend.php`

Esempio attuale:

```php
<?php

\Wonder\App\ModuleRouteRegistrar::registerFrontend($ROOT, $ROOT_APP);
```

I moduli e il progetto host registrano li' le route o i rispettivi file di route.

### 2. Handler

Un handler prepara i dati e renderizza una pagina con `View::make(...)->render()`.

Esempio:

```php
\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/home.php', [
    'TITLE' => 'Dashboard',
])->render();
```

### 3. View pagina

La pagina sceglie il layout e stampa solo il contenuto:

```php
<?php \Wonder\View\View::layout('frontend.main'); ?>

<section>
    <h1><?= e($TITLE ?? 'Titolo') ?></h1>
</section>

<?php \Wonder\View\View::end(); ?>
```

### 4. Layout

Il layout compone i partial:

```php
<?= \Wonder\View\View::component('frontend.layout.head') ?>
<?= \Wonder\View\View::component('frontend.layout.body-start') ?>
<?= $PAGE_CONTENT ?>
<?= \Wonder\View\View::component('frontend.layout.body-end') ?>
```

## Dove mettere la logica

### Logica dati

Se il componente dipende da dati applicativi o da una tabella SQL, la logica va in:

- `class/App/Model.php`
- `class/App/Models/...`
- `class/App/Resources/...`

Esempio reale:

- `Popup` usa `Popup::currentForPageKey()` e `Popup::modalPayloadForPageKey()`
- `Announcement` puo' essere letto da `Announcement::safeFind(...)`

Quindi il componente:

- legge un payload pronto
- non ricostruisce query duplicate
- non duplica preparazione file/immagini

### Logica di presentazione

La view puo' fare:

- piccoli fallback
- classi CSS dinamiche
- controllo su dati gia' pronti

La view non dovrebbe fare:

- nuove query complesse
- gestione upload
- regole di validazione
- logica di backend

## Esempi pratici

### Override del footer frontend nel progetto host

File:

- `custom/view/components/frontend/layout/footer.php`

Se esiste questo file, `View::component('frontend.layout.footer')` usera' quello al posto del core.

### Override del popup

File:

- `custom/view/components/frontend/overlay/popup.php`

Usalo solo se devi cambiare il markup o il comportamento visuale.
Se devi cambiare dati, filtri o regole di visibilita', correggi prima:

- `class/App/Models/Communications/Popup.php`
- `class/App/Resources/Communications/PopupResource.php`

### Announcement / annuncement

Il componente core e' ancora:

- `app/view/components/frontend/overlay/annuncement.php`

Il nome file contiene `annuncement` per compatibilita' storica.
Se devi allineare la logica, lavora prima sul model:

- `class/App/Models/Communications/Announcement.php`

## Regola pratica per Model e Resource

Quando lavori con la nuova architettura:

- `Model::tableSchema()` definisce SQL e colonne
- `Model::dataSchema()` definisce normalizzazione, upload, prepare, naming file
- `Resource::formSchema()` definisce gli input backend

Quindi non duplicare nel resource quello che vive gia' nel model, per esempio:

- `->storeAs(...)`
- `->prepare(...)`
- regole base di upload image/file

Il resource deve esprimere il form.
Il model deve esprimere i dati.

## Come aggiungere un nuovo componente frontend

### Caso 1: solo markup riusabile

Aggiungi un file nel core:

- `app/view/components/frontend/...`

e richiamalo con:

```php
<?= \Wonder\View\View::component('nome.componente', ['key' => $value]) ?>
```

Poi, se il progetto host deve customizzarlo, crea lo stesso path sotto:

- `custom/view/components/frontend/...`

### Caso 2: markup piu' dati dinamici

1. prepara i dati nel model o nell'handler
2. passa il payload al componente
3. fai render del componente con `View::component(...)`

Non mettere la query direttamente nel componente se la stessa logica deve essere riusata altrove.

## Come aggiungere una nuova pagina

### Nel core

1. registri o estendi la route frontend
2. prepari un handler in `app/http/...` oppure nel modulo
3. renderizzi una pagina in `app/view/pages/...`
4. la pagina apre un layout con `View::layout(...)`

### Nel progetto host

Se vuoi solo cambiare layout o partial:

1. non tocchi la route
2. fai override in `custom/view/layout/...` o `custom/view/components/...`

## Errori comuni

### 1. Documentare cartelle non piu' reali

Errore:

- `custom/components/...`
- `custom/lib/page.php`
- `renderPage(...)`
- `custom/config/navigation.php`

Questi riferimenti non descrivono il flusso corrente del core e non vanno usati come base per nuovo codice.

### 2. Mettere logica backend nella view

Errore:

- query SQL nel footer
- upload nel popup
- sanitize in un partial

Fix:

- sposta la logica in `class/App/...` o nell'handler

### 3. Duplicare regole tra model e resource

Errore:

- stesso `storeAs`, `prepare`, `dir`, `responsive` sia nel model sia nel resource

Fix:

- tieni la definizione dati nel model
- tieni il form backend nel resource

### 4. Fare override modificando il package

Errore:

- toccare direttamente il core per una personalizzazione di progetto

Fix:

- usa `custom/view/components/...`
- usa `custom/view/layout/...`

## Checklist rapida

Se devi fare una modifica in freecode, chiediti:

1. e' logica dati o e' solo rendering?
2. la modifica e' di progetto oppure deve vivere nel core?
3. mi basta un override `custom/view/...` o devo correggere `class/App/...`?
4. sto duplicando qualcosa che esiste gia' nel model o nel resource?

Se la risposta e' chiara, di solito il file giusto emerge subito.
