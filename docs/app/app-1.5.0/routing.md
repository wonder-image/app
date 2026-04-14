---
icon: route
---

# Routing

Questa è la struttura reale del routing oggi.

Il progetto usa:

- un solo front controller generato in `ROOT/handler/index.php`
- un solo dispatcher: `class/Http/RouteDispatcher.php`
- route dichiarative nei file `route.*.php`

Le route non vengono dedotte dalla URL. L'`area` viene dichiarata nella route.

## File da conoscere

### Core

- `class/Http/Route.php`
- `class/Http/RouteGroup.php`
- `class/Http/RouteDefinition.php`
- `class/Http/Router.php`
- `class/Http/RouteDispatcher.php`

### Config route

- `1.5.0/config/routes/route.api.php`
- `1.5.0/config/routes/route.backend.php`
- `custom/config/routes/route.api.php`
- `custom/config/routes/route.backend.php`
- `custom/config/routes/route.frontend.php`

### Bootstrap e publish

- `1.5.0/build/cli/update.php`
- `1.5.0/build/update/configuration_file.php`
- `1.5.0/function/helper.php`

## Come gira davvero

1. `php forge update --local` esegue `1.5.0/build/cli/update.php` e crea `ROOT/handler/index.php`
2. `.htaccess` manda lì le richieste senza file fisico
3. `RouteDispatcher` carica le route da:
   - `ROOT_APP/config/routes`
   - `ROOT/custom/config/routes`
4. il router trova la route
5. il dispatcher bootstrappa `wonder-image.php`
6. viene incluso l'handler della route

## Struttura di una route

Esempio reale backend:

```php
<?php

use Wonder\Http\Route;

Route::area('backend')
    ->prefix('/backend')
    ->response('html')
    ->theme('backend')
    ->guarded()
    ->name('backend.')
    ->group(function () use ($ROOT_APP) {

        Route::get('/', $ROOT_APP.'/http/backend/home.php')
            ->name('home')
            ->permit([]);

    });
```

Esempio reale API:

```php
<?php

use Wonder\Http\Route;

Route::area('api')
    ->prefix('/api')
    ->response('json')
    ->group(function () use ($ROOT_APP) {

        Route::name('app.')
            ->prefix('/app')
            ->group(function () use ($ROOT_APP) {

                Route::post('/update/', $ROOT_APP.'/http/api/app/update.php')
                    ->name('update');

            });

    });
```

## Metodi disponibili

### Definizione route

```php
Route::get($path, $handler);
Route::post($path, $handler);
Route::put($path, $handler);
Route::patch($path, $handler);
Route::delete($path, $handler);
Route::redirect($from, $to, 301);
```

### Contesto gruppo

```php
Route::area('backend')
    ->prefix('/backend')
    ->response('html')
    ->theme('backend')
    ->guarded()
    ->permit(['admin'])
    ->name('backend.')
    ->group(function () {});
```

### Modificatori route

```php
Route::get('/user/{id}/', $ROOT_APP.'/http/backend/user/view.php')
    ->name('user.view')
    ->permit(['admin'])
    ->where('id', '[0-9]+');
```

## Come aggiungere una nuova route

### Caso 1: nuova pagina backend

Supponiamo di voler creare `/backend/report/`.

#### Step 1: crea l'handler

File:

- `1.5.0/http/backend/report/index.php`

Esempio:

```php
<?php

\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/report/index.php', [
    'TITLE' => 'Report',
])->render();
```

#### Step 2: crea la view

File:

- `1.5.0/view/pages/backend/report/index.php`

Esempio:

```php
<?php \Wonder\View\View::layout('backend.main'); ?>

<div class="row g-3">
    <wi-card class="col-12">
        <h3>Report</h3>
    </wi-card>
</div>

<?php \Wonder\View\View::end(); ?>
```

#### Step 3: registra la route

File:

- `1.5.0/config/routes/route.backend.php`

Esempio:

```php
Route::get('/report/', $ROOT_APP.'/http/backend/report/index.php')
    ->name('report.index')
    ->permit([]);
```

Se la metti dentro il gruppo `backend.` il nome completo diventa:

```php
__r('backend.report.index')
```

### Caso 2: nuovo endpoint API

Supponiamo di voler creare `/api/app/ping/`.

#### Step 1: crea l'handler

File:

- `1.5.0/http/api/app/ping.php`

Esempio:

```php
<?php

use Wonder\Api\{ Endpoint, Handler, Response };

Handler::run('/api/app/ping/', 'POST', 'api_internal_user', function (Endpoint $CALL) {
    return Response::json([
        'success' => true,
        'status' => 200,
        'response' => 'pong',
    ]);
});
```

#### Step 2: registra la route

File:

- `1.5.0/config/routes/route.api.php`

Esempio:

```php
Route::post('/ping/', $ROOT_APP.'/http/api/app/ping.php')
    ->name('ping');
```

Se sei dentro il gruppo `app.` il nome completo diventa:

```php
__r('app.ping')
```

## Come modificare una route esistente

Hai tre leve vere:

### 1. Cambiare il path

Esempio:

```php
Route::get('/login/', $ROOT_APP.'/http/backend/account/login.php')
```

diventa:

```php
Route::get('/accesso/', $ROOT_APP.'/http/backend/account/login.php')
```

### 2. Cambiare il permesso

Esempio:

```php
->guarded(false)
```

oppure:

```php
->permit(['admin'])
```

### 3. Cambiare l'handler

Esempio:

```php
Route::get('/update/', $ROOT_APP.'/http/api/app/update.php')
```

diventa:

```php
Route::get('/update/', $ROOT.'/custom/http/api/app/update.php')
```

## Route nominate

Per generare un URL usa `__r()`.

Esempi:

```php
__r('backend.home');
__r('backend.account.login');
__r('app.update');
```

Con parametri:

```php
__r('backend.user.view', [
    'id' => 10,
]);
```

## Redirect, where e mask

### Redirect

```php
Route::redirect('/backend', '/backend/', 301);
```

Oppure:

```php
Route::get('/vecchio-path/', $ROOT_APP.'/http/backend/home.php')
    ->redirect('/backend/', 301);
```

### where

```php
Route::get('/user/{id}/', $ROOT_APP.'/http/backend/user/view.php')
    ->name('user.view')
    ->where('id', '[0-9]+');
```

### mask

`mask()` crea alias che fanno redirect verso il path canonico.

```php
Route::get('/prodotti/', $ROOT.'/custom/http/frontend/products.php')
    ->name('products.index')
    ->mask([
        '/products/',
        '/productos/',
        '/produkte/',
    ]);
```

In questo caso:

- `/prodotti/` usa l'handler vero
- `/products/` fa redirect a `/prodotti/`
- `/productos/` fa redirect a `/prodotti/`
- `/produkte/` fa redirect a `/prodotti/`

## Dove intervenire, file per file

### Aggiungere o cambiare route backend

- `1.5.0/config/routes/route.backend.php`
- `custom/config/routes/route.backend.php`

### Aggiungere o cambiare route API

- `1.5.0/config/routes/route.api.php`
- `custom/config/routes/route.api.php`

### Aggiungere route frontend custom

- `custom/config/routes/route.frontend.php`

Nota:

oggi nel package ci sono `route.api.php` e `route.backend.php`.
Il frontend routed può essere aggiunto in `custom` con lo stesso formato.

### Cambiare il dispatcher

- `class/Http/RouteDispatcher.php`
- `class/Http/Router.php`

### Cambiare il file generato e il rewrite

- `1.5.0/build/cli/update.php`
- `1.5.0/build/update/configuration_file.php`

## Errori comuni

### 1. Route aggiunta ma URL non raggiungibile

Cause tipiche:

- il file handler non esiste davvero
- il nome del file route non è `route.*.php`
- non hai rieseguito il publish/update sul progetto host

Controlla:

- `ROOT/handler/index.php`
- `.htaccess`
- path dell'handler nella route

### 2. `__r()` restituisce stringa vuota

Cause tipiche:

- nome route sbagliato
- prefisso `name('backend.')` o `name('app.')` non considerato
- route non ancora caricata nel contesto corrente

Controlla sempre il nome completo finale.

### 3. Route backend pubblica per errore

Se dimentichi:

```php
->guarded()
```

la route non è privata.

Per il backend, di default usa sempre un gruppo `guarded()` e apri solo le eccezioni come login e recovery.

### 4. 404 HTML invece di JSON

Succede se il path non matcha nessuna route API dichiarata.

Quindi:

- prima crea la route
- poi testa l'endpoint

### 5. Route cambiata ma vince ancora un file fisico legacy

Il rewrite passa al router solo se il file fisico non esiste.

Se c'è ancora una directory legacy in root, quella può battere la route nuova.

Controlla `1.5.0/build/cli/update.php` e pulisci i path legacy che non devono più esistere.

## Best practice

- Tieni la route corta: path, nome, permessi, handler. Niente logica lì dentro.
- Metti la logica nell'handler `http/...`.
- Metti il markup nella `view/...`.
- Usa `__r()` invece di concatenare path a mano.
- Per backend usa un gruppo principale con `guarded()` e `name('backend.')`.
- Per API usa `response('json')` e handler con `Wonder\Api\Handler`.
- Se personalizzi il progetto, metti prima il codice in `custom/` invece di toccare il core.
- Se una route sostituisce un file fisico legacy, elimina il file legacy nel publish/update.
- Evita redirect in `.htaccess` quando possono stare nel routing applicativo.

## Formula pratica da ricordare

Per aggiungere una nuova pagina o endpoint fai sempre questo:

1. crea l'handler in `http/...`
2. crea la view se serve in `view/...`
3. registra la route in `config/routes/...`
4. usa `__r()` dove ti serve il link
5. se sostituisci un file legacy, ripuliscilo nel publish/update
