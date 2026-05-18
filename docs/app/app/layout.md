---
icon: table-cells
---

# Layout E View

Questa è la struttura reale di view e layout oggi.

Il flusso corretto è questo:

1. la route punta a un handler in `http/...`
2. l'handler prepara i dati
3. l'handler chiama `Wonder\View\View::make(...)->render()`
4. la pagina view sceglie il layout con `View::layout(...)`
5. la pagina chiude con `View::end()`

## File da conoscere

### Renderer

- `class/View/View.php`

### Layout core

- `app/view/layout/backend/base.php`
- `app/view/layout/backend/main.php`
- `app/view/layout/backend/auth.php`
- `app/view/layout/frontend/base.php`
- `app/view/layout/frontend/main.php`

### View pages

- `app/view/pages/backend/home.php`
- `app/view/pages/backend/account/login.php`
- `app/view/pages/backend/account/index.php`
- `app/view/pages/frontend/default/under_construction.php`
- `app/view/pages/frontend/default/under_maintenance.php`

### Componenti layout attivi

- `app/view/components/backend/layout/head.php`
- `app/view/components/backend/layout/body-start.php`
- `app/view/components/backend/layout/body-end.php`
- `app/view/components/backend/layout/header.php`
- `app/view/components/backend/layout/footer.php`
- `app/view/components/frontend/layout/head.php`
- `app/view/components/frontend/layout/body-start.php`
- `app/view/components/frontend/layout/body-end.php`
- `app/view/components/frontend/layout/header.php`
- `app/view/components/frontend/layout/footer.php`

## Struttura target

La direzione corretta del refactor layout e view e':

- layout chiamati con `View::layout('frontend.main')` e `View::layout('backend.main')`
- partial HTML riusabili spostati in `app/view/components/...`
- bootstrap non renderizzabile separato dai componenti view
- nessun nuovo uso di `app/utility/*` o `custom/utility/*`

### Layout target

- `app/view/layout/frontend/base.php`
- `app/view/layout/frontend/main.php`
- `app/view/layout/backend/base.php`
- `app/view/layout/backend/main.php`
- `app/view/layout/backend/auth.php`

Il naming target evita il suffisso `_layout` quando non serve.

### Componenti layout target

- `app/view/components/frontend/layout/head.php`
- `app/view/components/frontend/layout/body-start.php`
- `app/view/components/frontend/layout/body-end.php`
- `app/view/components/frontend/layout/header.php`
- `app/view/components/frontend/layout/footer.php`
- `app/view/components/backend/layout/head.php`
- `app/view/components/backend/layout/body-start.php`
- `app/view/components/backend/layout/body-end.php`
- `app/view/components/backend/layout/header.php`
- `app/view/components/backend/layout/footer.php`

### Bootstrap target

I file `set-up.php` non sono componenti view. La destinazione corretta e':

- `app/bootstrap/frontend.php`
- `app/bootstrap/backend.php`

Questi file restano PHP runtime/bootstrap e non devono essere messi in `view/components`.

### Navigazione backend

La navigazione backend non deve piu' passare da una variabile globale `NAV_BACKEND`.

La sorgente corretta e':

- `Wonder\Backend\Support\BackendNavigation::all()`

Il bootstrap backend carica tema e dipendenze, mentre i componenti/layout che devono leggere la navigazione la recuperano direttamente da `BackendNavigation`.

### Convenzioni nei componenti layout

Per i partial in `app/view/components/*` la convenzione pratica e':

- usare `e(...)` per testo e attributi HTML
- usare `json_encode(...)` quando un valore PHP entra in uno script inline JavaScript
- preferire una fase iniziale di preparazione dati PHP e poi markup HTML leggibile, invece di grandi `echo` multilinea
- lasciare raw solo output esplicitamente HTML o snippet gia' prodotti da helper dedicati

### Override target nel progetto host

I vecchi hook:

- `custom/utility/frontend/*`
- `custom/utility/backend/*`

devono essere sostituiti con:

- `custom/view/components/frontend/layout/*`
- `custom/view/components/backend/layout/*`

### Mapping utility -> target

- `app/utility/frontend/head.php` -> `app/view/components/frontend/layout/head.php`
- `app/utility/frontend/body-start.php` -> `app/view/components/frontend/layout/body-start.php`
- `app/utility/frontend/body-end.php` -> `app/view/components/frontend/layout/body-end.php`
- `app/utility/frontend/set-up.php` -> `app/bootstrap/frontend.php`
- `app/utility/backend/head.php` -> `app/view/components/backend/layout/head.php`
- `app/utility/backend/body-start.php` -> `app/view/components/backend/layout/body-start.php`
- `app/utility/backend/body-end.php` -> `app/view/components/backend/layout/body-end.php`
- `app/utility/backend/header.php` -> `app/view/components/backend/layout/header.php`
- `app/utility/backend/footer.php` -> `app/view/components/backend/layout/footer.php`
- `app/utility/backend/set-up.php` -> `app/bootstrap/backend.php`

### Riferimenti da migrare

I punti principali da aggiornare durante il refactor sono:

- `wonder-image.php`
- `app/view/layout/frontend/*`
- `app/view/layout/backend/*`
- `app/html/default/*`
- `app/html/backend/list.php`
- `app/build/src/docs/*`

Le vecchie pagine statiche `app/html/default/*` sono state spostate nel sistema view sotto `app/view/pages/frontend/default/*`.

L'obiettivo finale e' avere i layout che usano `View::component(...)` per head/body/header/footer e non includono piu' file in `app/utility/*`.

## Come gira davvero

### Handler

Esempio reale:

- `app/http/backend/account/login.php`

```php
<?php

\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/account/login.php', [
    'TITLE' => 'Login',
    'ALERT' => $ALERT ?? null,
    'fieldUsername' => $_POST['username'] ?? null,
])->render();
```

Qui devi fare solo:

- logica
- query
- redirect
- dati da passare alla view

Non devi mettere:

- HTML completo
- `head`
- `body`
- include del layout

### Pagina

Esempio reale:

- `app/view/pages/backend/account/login.php`

```php
<?php \Wonder\View\View::layout('backend.auth'); ?>

<form method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
    <wi-card>
        <div class="col-12">
            <?=text('Username', 'username', 'required', $fieldUsername ?? ''); ?>
        </div>

        <div class="col-12">
            <?=password('Password', 'password', 'required'); ?>
        </div>

        <div class="d-grid col-8 mx-auto">
            <?=submit('Accedi', 'login'); ?>
        </div>
    </wi-card>
</form>

<?php \Wonder\View\View::end(); ?>
```

La pagina:

- sceglie il layout
- stampa solo il contenuto
- chiude il layout

### Layout

Esempio reale:

- `app/view/layout/backend/auth.php`

```php
<?php \Wonder\View\View::layout('backend.base'); ?>

    <div class="position-absolute w-75 top-50 start-50 translate-middle" style="max-width: 400px">
        <?=$PAGE_CONTENT?>
    </div>

<?php \Wonder\View\View::end(); ?>
```

Esempio base backend:

- `app/view/layout/backend/base.php`

Qui stanno:

- `<html>`
- `<head>`
- `View::component('backend.layout.head')`
- `View::component('backend.layout.body-start')`
- `View::component('backend.layout.body-end')`

## Come aggiungere una nuova view

Supponiamo di voler creare una pagina backend `report`.

### Step 1: handler

File:

- `app/http/backend/report/index.php`

```php
<?php

\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/report/index.php', [
    'TITLE' => 'Report',
])->render();
```

### Step 2: pagina

File:

- `app/view/pages/backend/report/index.php`

```php
<?php \Wonder\View\View::layout('backend.main'); ?>

<div class="row g-3">
    <wi-card class="col-12">
        <h3>Report</h3>
    </wi-card>
</div>

<?php \Wonder\View\View::end(); ?>
```

### Step 3: route

Registra la route nel file route corretto.

## Come aggiungere un nuovo layout

Supponiamo di voler creare un layout backend minimale per una pagina speciale.

### Step 1: crea il file layout

File:

- `app/view/layout/backend/minimal_layout.php`

Esempio:

```php
<?php \Wonder\View\View::layout('backend.base'); ?>

    <div class="container py-5">
        <?=$PAGE_CONTENT?>
    </div>

<?php \Wonder\View\View::end(); ?>
```

### Step 2: usalo nella pagina

File:

- `app/view/pages/backend/special/index.php`

```php
<?php \Wonder\View\View::layout('backend.minimal'); ?>

<wi-card>
    <h3>Pagina speciale</h3>
</wi-card>

<?php \Wonder\View\View::end(); ?>
```

Nome layout:

```php
backend.minimal
```

viene risolto in:

- `custom/view/layout/backend/minimal.php`
- `custom/view/layout/backend/minimal_layout.php`
- `app/view/layout/backend/minimal.php`
- `app/view/layout/backend/minimal_layout.php`

in quest'ordine.

Quindi i layout sono espandibili e sovrascrivibili.

## Come modificare un layout esistente

Hai due strade.

### 1. Modifica diretta del core

Intervieni in:

- `app/view/layout/backend/base.php`
- `app/view/layout/backend/main_layout.php`
- `app/view/layout/backend/auth_layout.php`
- `app/view/layout/frontend/base.php`
- `app/view/layout/frontend/main_layout.php`

Va bene se stai lavorando al package.

### 2. Override nel progetto host

Metti il file in:

- `custom/view/layout/...`

Esempio:

- `custom/view/layout/backend/main_layout.php`

In questo modo non tocchi il core e il renderer userà prima il custom.

## Dove intervenire, file per file

### Vuoi cambiare il markup comune backend

- `app/view/layout/backend/base.php`
- `app/view/layout/backend/main_layout.php`
- `app/view/layout/backend/auth_layout.php`

### Vuoi cambiare il markup comune frontend

- `app/view/layout/frontend/base.php`
- `app/view/layout/frontend/main_layout.php`

### Vuoi cambiare una pagina singola

- `app/view/pages/...`

### Vuoi cambiare il renderer

- `class/View/View.php`

### Vuoi cambiare `head`, `body-start`, `body-end`

- `app/utility/backend/*`
- `app/utility/frontend/*`
- `custom/utility/backend/*`
- `custom/utility/frontend/*`

## Compatibilità legacy importante

Il renderer mantiene compatibilità con alcune parti legacy:

- `ALERT`
- `VALUES`

Questo serve perché:

- `body-end.php` usa ancora `alert()`
- gli helper `text()`, `phone()`, `email()`, `password()` leggono ancora `global $VALUES`

Quindi se nella view usi gli helper input legacy, passa sempre i dati giusti dall'handler.

Esempio:

```php
\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/account/password-recovery.php', [
    'TITLE' => $TITLE,
    'ALERT' => $ALERT ?? null,
    'VALUES' => $_POST,
])->render();
```

## Errori comuni

### 1. Il layout non viene trovato

Cause tipiche:

- nome layout sbagliato
- file chiamato male
- file messo nella cartella sbagliata

Esempio corretto:

```php
\Wonder\View\View::layout('backend.auth');
```

cerca:

- `backend/auth.php`
- `backend/auth_layout.php`

### 2. Gli input non mantengono i valori

Cause tipiche:

- non passi `VALUES`
- stai usando gli helper legacy che leggono `global $VALUES`

### 3. Gli alert non compaiono

Cause tipiche:

- non passi `ALERT`
- la pagina non chiude correttamente il layout

### 4. Pagina vuota o markup spezzato

Cause tipiche:

- manca `View::end()`
- layout annidati aperti male

## Best practice

- L'handler prepara dati, non stampa HTML.
- La pagina sceglie il layout e stampa il contenuto.
- Il layout gestisce solo struttura comune.
- Non mettere query o logica pesante nella view.
- Se puoi, usa override in `custom/` invece di toccare il core.
- Riusa `backend.main`, `backend.auth`, `frontend.main` prima di creare layout nuovi.
- Se il layout nuovo differisce poco da uno esistente, estendi quello esistente invece di duplicarlo.
- Se usi componenti legacy, passa sempre `ALERT` e `VALUES` in modo esplicito.
