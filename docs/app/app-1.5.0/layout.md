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

- `1.5.0/view/layout/backend/base.php`
- `1.5.0/view/layout/backend/main_layout.php`
- `1.5.0/view/layout/backend/auth_layout.php`
- `1.5.0/view/layout/frontend/base.php`
- `1.5.0/view/layout/frontend/main_layout.php`

### View pages

- `1.5.0/view/pages/backend/home.php`
- `1.5.0/view/pages/backend/account/login.php`
- `1.5.0/view/pages/backend/account/index.php`

### Utility legacy ancora usate dai layout

- `1.5.0/utility/backend/head.php`
- `1.5.0/utility/backend/body-start.php`
- `1.5.0/utility/backend/body-end.php`
- `1.5.0/utility/frontend/head.php`
- `1.5.0/utility/frontend/body-start.php`
- `1.5.0/utility/frontend/body-end.php`

## Come gira davvero

### Handler

Esempio reale:

- `1.5.0/http/backend/account/login.php`

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

- `1.5.0/view/pages/backend/account/login.php`

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

- `1.5.0/view/layout/backend/auth_layout.php`

```php
<?php \Wonder\View\View::layout('backend.base'); ?>

    <div class="position-absolute w-75 top-50 start-50 translate-middle" style="max-width: 400px">
        <?=$PAGE_CONTENT?>
    </div>

<?php \Wonder\View\View::end(); ?>
```

Esempio base backend:

- `1.5.0/view/layout/backend/base.php`

Qui stanno:

- `<html>`
- `<head>`
- `utility/backend/head.php`
- `utility/backend/body-start.php`
- `utility/backend/body-end.php`

## Come aggiungere una nuova view

Supponiamo di voler creare una pagina backend `report`.

### Step 1: handler

File:

- `1.5.0/http/backend/report/index.php`

```php
<?php

\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/report/index.php', [
    'TITLE' => 'Report',
])->render();
```

### Step 2: pagina

File:

- `1.5.0/view/pages/backend/report/index.php`

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

- `1.5.0/view/layout/backend/minimal_layout.php`

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

- `1.5.0/view/pages/backend/special/index.php`

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
- `1.5.0/view/layout/backend/minimal.php`
- `1.5.0/view/layout/backend/minimal_layout.php`

in quest'ordine.

Quindi i layout sono espandibili e sovrascrivibili.

## Come modificare un layout esistente

Hai due strade.

### 1. Modifica diretta del core

Intervieni in:

- `1.5.0/view/layout/backend/base.php`
- `1.5.0/view/layout/backend/main_layout.php`
- `1.5.0/view/layout/backend/auth_layout.php`
- `1.5.0/view/layout/frontend/base.php`
- `1.5.0/view/layout/frontend/main_layout.php`

Va bene se stai lavorando al package.

### 2. Override nel progetto host

Metti il file in:

- `custom/view/layout/...`

Esempio:

- `custom/view/layout/backend/main_layout.php`

In questo modo non tocchi il core e il renderer userà prima il custom.

## Dove intervenire, file per file

### Vuoi cambiare il markup comune backend

- `1.5.0/view/layout/backend/base.php`
- `1.5.0/view/layout/backend/main_layout.php`
- `1.5.0/view/layout/backend/auth_layout.php`

### Vuoi cambiare il markup comune frontend

- `1.5.0/view/layout/frontend/base.php`
- `1.5.0/view/layout/frontend/main_layout.php`

### Vuoi cambiare una pagina singola

- `1.5.0/view/pages/...`

### Vuoi cambiare il renderer

- `class/View/View.php`

### Vuoi cambiare `head`, `body-start`, `body-end`

- `1.5.0/utility/backend/*`
- `1.5.0/utility/frontend/*`
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
