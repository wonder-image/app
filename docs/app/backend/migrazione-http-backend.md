# Migrazione backend al sistema HTTP `Route -> Handler -> View`

## Stato reale del progetto

La situazione oggi non e' uniforme:

- il router nuovo esiste ed e' attivo in `class/Http/RouteDispatcher.php`
- il backend nuovo esiste solo per:
  - `app/http/backend/home.php`
  - `app/http/backend/account/*`
  - `app/view/layout/backend/*`
  - `app/view/pages/backend/*`
- il backend legacy principale vive ancora in:
  - `app/build/src/backend/**`
  - `app/html/backend/index.php`
  - `app/html/backend/list.php`
  - `app/utility/backend/*`
  - `app/api/backend/*`

Numeri utili rilevati nel package:

- `59` file legacy sotto `app/build/src/backend`
- `7` handler HTTP backend gia' migrati sotto `app/http/backend`
- `6` view backend nuove sotto `app/view/pages/backend`
- `13` endpoint backend AJAX legacy sotto `app/api/backend`

Questo significa che oggi convivono due modelli:

1. backend nuovo:
   `Route -> app/http/backend/*.php -> app/view/pages/backend/*.php`
2. backend legacy:
   file pubblicati o raggiunti per path storico che bootstrapano direttamente `wonder-image.php` e stampano HTML completo

## Flusso attuale

### Router e bootstrap

Il flusso HTTP nuovo oggi e' questo:

1. `handler/index.php` istanzia `Wonder\Http\RouteDispatcher`
2. il dispatcher carica `app/config/routes/route.*.php`
3. la route seleziona l'handler
4. il dispatcher condivide le variabili legacy e chiama `wonder-image.php`
5. `wonder-image.php` carica:
   - funzioni legacy
   - config
   - servizi
   - middleware
   - `app/utility/backend/set-up.php` se l'area e' `backend`
6. l'handler viene incluso

File chiave:

- `class/Http/RouteDispatcher.php`
- `wonder-image.php`
- `app/config/routes/route.backend.php`
- `app/utility/backend/set-up.php`

### Rendering backend nuovo

Il backend nuovo e' corretto come direzione, ma ancora dipendente dalla legacy:

- gli handler preparano dati e chiamano `Wonder\View\View::make(...)`
- le view scelgono il layout con `View::layout(...)`
- i layout includono ancora:
  - `app/utility/backend/head.php`
  - `app/utility/backend/body-start.php`
  - `app/utility/backend/header.php`
  - `app/utility/backend/footer.php`
  - `app/utility/backend/body-end.php`

File chiave:

- `app/http/backend/home.php`
- `app/http/backend/account/index.php`
- `app/view/layout/backend/base.php`
- `app/view/layout/backend/main_layout.php`
- `app/view/layout/backend/auth_layout.php`

### Rendering backend legacy

Il backend legacy ha quattro pattern principali.

#### 1. Pagine complete con HTML inline

Esempi:

- `app/build/src/backend/home/index.php`
- `app/build/src/backend/app/config/corporate-data/index.php`
- `app/build/src/backend/app/media/upload-massive/index.php`

Pattern:

- set flag globali (`$BACKEND`, `$PRIVATE`, `$PERMIT`)
- include diretto di `wonder-image.php`
- query/logica inline
- `<html>`, `<head>`, `<body>` stampati nello stesso file
- include manuale di `utility/backend/*`

#### 2. CRUD con hub comune

Esempi:

- `app/build/src/backend/app/config/legal-documents/index.php`
- `app/build/src/backend/app/config/user/index.php`

Pattern:

- `require_once "set-up.php"`
- eventuale pre-processing locale
- `require_once $ROOT_APP."/html/backend/index.php"`
- HTML form inline nel file pagina

#### 3. Liste tabellari con hub comune

Esempi:

- `app/build/src/backend/app/config/user/list.php`
- `app/build/src/backend/app/config/legal-documents/list.php`
- `app/build/src/backend/app/log/email/list.php`

Pattern:

- `require_once "set-up.php"`
- `require_once $ROOT_APP."/html/backend/list.php"`
- il file comune costruisce la tabella `Wonder\Backend\Table\Table`
- il rendering DataTable dipende da endpoint AJAX legacy

#### 4. Detail / view / download

Esempi:

- `app/build/src/backend/app/config/legal-documents/view.php`
- `app/build/src/backend/app/log/email/view.php`
- `app/build/src/backend/app/config/legal-documents/download.php`

Pattern:

- logica inline
- markup inline
- redirect e output binario gestiti nello stesso file

## Problemi strutturali principali

### 1. Nessuna separazione netta tra handler e view

Il nuovo backend la introduce, il legacy no.

Nei file legacy si trovano insieme:

- bootstrap
- autorizzazione
- query SQL
- trasformazioni dati
- redirect
- HTML

Esempio reale:

- `app/build/src/backend/app/config/legal-documents/index.php`

### 2. Layout nuovo sopra utility legacy

Anche le view nuove passano ancora da `app/utility/backend/*`.

Questo non e' un problema da risolvere con un rewrite immediato, ma indica che il vero runtime backend e' ancora li':

- tema
- dipendenze JS/CSS
- navigazione
- toast alert
- modal

### 3. Dipendenza pesante da global state

Le view e gli helper backend leggono direttamente:

- `$VALUES`
- `$ALERT`
- `$PATH`
- `$PAGE`
- `$USER`
- `$DEFAULT`
- `$NAV_BACKEND`

Esempi:

- `app/function/backend/input.php`
- `app/function/components/alert.php`
- `app/utility/backend/header.php`

### 4. AJAX backend fuori dal router nuovo

Le tabelle e molte azioni usano ancora endpoint fisici:

- `/app/api/backend/list/table.php`
- `/app/api/backend/change/boolean.php`
- `/app/api/backend/delete.php`
- `/app/api/backend/move.php`

Il coupling passa da:

- `class/App/Path.php`
- `class/Backend/Table/Field.php`
- `app/function/backend/plugin.php`

### 5. Convenzioni di URL miste

Oggi convivono:

- URL canonical nuove, es. `/backend/account/login/`
- URL storiche con file fisico, es. `/backend/app/config/user/list.php`

Se si migra senza bridge, si rompono:

- link interni
- bookmark
- redirect di ritorno
- pagine pubblicate in installazioni gia' esistenti

### 6. Duplicazione elevata

La duplicazione e' evidente in:

- `<html>/<head>/<body>` ripetuti in decine di file legacy
- include ripetuti di `utility/backend/*`
- logica CRUD ripetuta
- pattern di list/detail quasi identici

## Direzione scelta

### Scelta

La direzione migliore per questo progetto e':

- mantenere il sistema `View` custom attuale
- usare handler PHP sottili come controller
- introdurre un bootstrap backend dedicato ma leggero
- introdurre bridge mirati per far convivere legacy e nuovo
- migrare gradualmente le URL backend sul router nuovo
- lasciare Blade fuori

### Perche' questa e' la scelta giusta

#### Non usare Blade

Blade qui non risolve il problema vero.

I problemi reali sono:

- globali legacy
- routing storico
- utility backend ancora centralizzate
- endpoint AJAX fuori dal router nuovo
- pagine legacy con bootstrap e HTML nello stesso file

Blade aggiungerebbe:

- compilazione template
- cache template
- nuova sintassi
- doppia convenzione di rendering
- nuova dipendenza concettuale

senza eliminare nessuno dei coupling sopra.

In piu' oggi il renderer `View` e' gia' allineato con il progetto:

- `View::make(...)`
- `View::layout(...)`
- `View::end()`

Quindi la scelta piu' pragmatica e' consolidare questo flusso, non sostituirlo.

#### Usare bootstrap dedicato backend

Si', ma senza cambiare il bootstrap globale del framework.

Il modo corretto e':

- incapsulare la logica di `app/utility/backend/set-up.php` in una classe
- lasciare `wonder-image.php` e il dispatcher invariati nel comportamento
- fare in modo che `app/utility/backend/set-up.php` diventi un thin wrapper

Questo da':

- un bordo chiaro del runtime backend
- meno side effect sparsi
- compatibilita' piena

#### Usare handler/controller sottili

Non serve introdurre un framework controller completo.

Per questo progetto basta:

- route dichiarativa
- handler file-based sotto `app/http/backend/**`
- piccole classi di supporto riusabili sotto `class/Backend/Support/**`
- view PHP semplici sotto `app/view/pages/backend/**`

Questo e' coerente con lo stile attuale del codice e con il livello di complessita' reale del progetto.

## Architettura target

## Flusso target

Il flusso target deve essere questo:

1. `Route` risolve URL e permessi
2. l'handler backend prepara dati e decide redirect / response
3. l'handler usa support class leggere solo se servono
4. la view stampa solo contenuto
5. il layout backend gestisce struttura comune
6. gli endpoint AJAX passano dal router API quando vengono migrati

Forma pratica:

```text
Route
  -> handler/controller (app/http/backend/**)
  -> service/support opzionale (class/Backend/Support/**)
  -> View (app/view/pages/backend/**)
  -> layout (app/view/layout/backend/**)
```

## Struttura cartelle proposta

```text
class/
  Backend/
    Support/
      Bootstrap.php
      LegacyTablePage.php
      RouteCleanup.php

app/
  config/
    backend/
      migrated.php
    routes/
      route.backend.php
      route.api.php

  http/
    backend/
      home.php
      account/
      config/
        user/
          list.php
          index.php
        legal-documents/
          list.php
          index.php
          view.php
          download.php
      log/
      media/

    api/
      backend/
        table.php
        change-boolean.php
        delete.php
        move.php

  view/
    layout/
      backend/
        base.php
        main_layout.php
        auth_layout.php

    pages/
      backend/
        shared/
          table.php
        config/
          user/
            list.php
            index.php
          legal-documents/
            list.php
            index.php
            view.php
        log/
        media/

  utility/
    backend/
      set-up.php
```

## File da creare o modificare

### Da creare

- `class/Backend/Support/Bootstrap.php`
- `class/Backend/Support/LegacyTablePage.php`
- `app/config/backend/migrated.php`
- `app/http/backend/config/user/list.php`
- `app/http/backend/config/legal-documents/list.php`
- `app/view/pages/backend/shared/table.php`
- progressivamente gli handler/view dei moduli migrati

### Da modificare

- `app/utility/backend/set-up.php`
- `app/config/routes/route.backend.php`
- `app/config/routes/route.api.php`
- `app/build/cli/update.php`
- `class/App/Path.php`

## Bridge minimi da introdurre

### 1. Bootstrap backend dedicato

Obiettivo:

- spostare la logica di setup backend in un punto riusabile
- non cambiare il comportamento runtime

File nuovo:

- `class/Backend/Support/Bootstrap.php`

```php
<?php

namespace Wonder\Backend\Support;

use Wonder\App\Dependencies;
use Wonder\App\LegacyGlobals;
use Wonder\App\Theme;

final class Bootstrap
{
    public static function run(): void
    {
        Theme::set('bootstrap');

        $permits = LegacyGlobals::get('PERMITS');
        $navBackend = [];

        $defaultNavTop = [
            [
                'title' => 'Home',
                'folder' => 'home',
                'icon' => 'bi-house-door',
                'file' => $permits['backend']['links']['home'] ?? '/backend/',
                'authority' => [],
                'subnavs' => [],
            ],
        ];

        $defaultNavBottom = self::defaultNavBottom();

        Dependencies::jquery()
            ::jqueryPlugin()
            ::moment()
            ::bootstrap()
            ::bootstrapIcons()
            ::bootstrapDatepicker()
            ::jszip()
            ::datatables()
            ::quilljs()
            ::editorjs()
            ::filepond()
            ::autonumeric()
            ::jstree()
            ::select2()
            ::wiBackend();

        $root = (string) LegacyGlobals::get('ROOT', '');

        if ($root !== '' && file_exists($root.'/custom/utility/backend/set-up.php')) {
            include $root.'/custom/utility/backend/set-up.php';
        }

        LegacyGlobals::share([
            'NAV_BACKEND' => array_merge(
                $defaultNavTop,
                is_array($navBackend) ? $navBackend : [],
                $defaultNavBottom
            ),
        ]);
    }

    private static function defaultNavBottom(): array
    {
        return [
            [
                'title' => 'Media',
                'folder' => 'media',
                'icon' => 'bi-image',
                'authority' => ['admin'],
                'subnavs' => [
                    [
                        'title' => 'Logo',
                        'folder' => 'app/media/logos',
                        'file' => '',
                        'authority' => ['admin'],
                    ],
                ],
            ],
        ];
    }
}
```

Wrapper compatibile:

File modificato:

- `app/utility/backend/set-up.php`

```php
<?php

\Wonder\Backend\Support\Bootstrap::run();
```

Nota:
la classe nuova deve inizialmente copiare 1:1 la logica attuale di `set-up.php`.
Il refactor interno viene dopo.

### 2. Bridge per liste legacy

Questo e' il bridge con miglior rapporto costo/beneficio, perche' molte pagine legacy sono liste DataTable.

File nuovo:

- `class/Backend/Support/LegacyTablePage.php`

```php
<?php

namespace Wonder\Backend\Support;

use RuntimeException;
use Wonder\App\LegacyGlobals;

final class LegacyTablePage
{
    public static function make(string $setUpFile): array
    {
        if (!file_exists($setUpFile)) {
            throw new RuntimeException("Set-up backend non trovato: {$setUpFile}");
        }

        $rootApp = (string) LegacyGlobals::get('ROOT_APP', '');

        if ($rootApp === '') {
            throw new RuntimeException('ROOT_APP non disponibile.');
        }

        require $setUpFile;
        require $rootApp.'/html/backend/list.php';

        return [
            'table_html' => $TABLE->generate(),
        ];
    }
}
```

Questo non risolve tutta la legacy, ma permette di spostare subito molte liste dentro:

- route nuove
- handler nuovi
- layout nuovi

senza riscrivere subito `Wonder\Backend\Table\Table`.

### 3. Cleanup progressivo dei path legacy pubblicati

Questa parte e' essenziale per non rompere installazioni esistenti.

Oggi `app/build/cli/update.php` rimuove gia' il path legacy pubblicato di `account`:

- elimina `ROOT/backend/account/`

Bisogna generalizzare questo pattern.

File nuovo:

- `app/config/backend/migrated.php`

```php
<?php

return [
    '/backend/account',
    '/backend/app/config/user',
    '/backend/app/config/legal-documents',
];
```

Modifica proposta:

- `app/build/cli/update.php`

```php
<?php

$migratedBackendPaths = file_exists($ROOT.'/vendor/wonder-image/app/app/config/backend/migrated.php')
    ? require $ROOT.'/vendor/wonder-image/app/app/config/backend/migrated.php'
    : [];

foreach ($migratedBackendPaths as $relativePath) {
    $absolutePath = $ROOT.$relativePath;

    if (is_dir($absolutePath)) {
        deleteDir($absolutePath);
        continue;
    }

    if (file_exists($absolutePath)) {
        unlink($absolutePath);
    }
}
```

Regola:

- quando un modulo e' migrato a route nuove, si aggiunge il suo path legacy pubblicato a questa lista
- finche' non e' nella lista, il modulo legacy continua a funzionare

## Strategia di migrazione progressiva

## Principi

- nessun big bang rewrite
- i moduli nuovi devono convivere con quelli legacy
- ogni modulo migrato deve poter essere rilasciato da solo
- le URL storiche devono continuare a funzionare
- gli endpoint AJAX legacy vanno mantenuti finche' la pagina che li usa non viene migrata

## Ordine consigliato

### Fase 1. Consolidare il runtime backend

Obiettivo:

- fermare la proliferazione della logica backend
- introdurre i bridge minimi

Lavori:

1. creare `class/Backend/Support/Bootstrap.php`
2. trasformare `app/utility/backend/set-up.php` in wrapper
3. creare `class/Backend/Support/LegacyTablePage.php`
4. creare `app/config/backend/migrated.php`
5. modificare `app/build/cli/update.php` per il cleanup progressivo

Questa fase non cambia il comportamento utente.

### Fase 2. Migrare tutte le liste

Perche' prima le liste:

- sono numerose
- sono strutturalmente simili
- hanno il bridge piu' semplice
- riducono subito tanto HTML duplicato

Moduli candidati iniziali:

- `app/config/user/list`
- `app/config/legal-documents/list`
- `app/log/email/list`
- `app/log/auth-users/list`
- `app/log/consent/list`
- `app/media/images/list`
- `app/media/icons/list`
- `app/media/documents/list`

Pattern target:

- `app/http/backend/.../list.php`
- `app/view/pages/backend/shared/table.php`
- route backend dedicata
- mask per l'URL legacy `.php`

### Fase 3. Migrare detail/view/download

Questi file sono meno frequenti e quasi sempre read-only.

Ordine:

1. `view.php`
2. `download.php`

Per i download:

- non serve una view
- basta un handler backend che imposti header e stampa contenuto

### Fase 4. Migrare CRUD e pagine form

Questa e' la fase piu' lunga, perche' i form sono meno uniformi.

Qui non conviene fare un meta-framework nuovo.
Conviene:

- spostare la logica dal file legacy all'handler
- riusare gli helper input legacy nelle view nuove
- continuare a passare `VALUES` e `ALERT`
- mantenere `formToArray()` e funzioni esistenti finche' non vengono rimpiazzate

### Fase 5. Migrare gli endpoint AJAX legacy

Solo dopo che le pagine dipendenti sono migrate.

Ordine:

1. endpoint DataTable
2. toggle booleani
3. delete/move
4. file delete/move

Quando si migra questa parte, va aggiornato:

- `class/App/Path.php`
- `class/Backend/Table/Field.php`
- eventuali helper con URL hardcoded

## URL e compatibilita' legacy

La regola deve essere:

- nuova route con URL canonical senza `.php`
- route mask per URL storico con `.php`
- redirect 301 verso il canonical dove ha senso

Esempio reale per una lista migrata:

```php
Route::get('/app/config/user/list/', $ROOT_APP.'/http/backend/config/user/list.php')
    ->name('config.user.list')
    ->permit([])
    ->mask('/app/config/user/list.php', 301);
```

Esempio reale per una pagina form migrata:

```php
Route::get('/app/config/legal-documents/', $ROOT_APP.'/http/backend/config/legal-documents/index.php')
    ->name('config.legal-documents.index')
    ->permit(['admin'])
    ->mask('/app/config/legal-documents/index.php', 301);

Route::post('/app/config/legal-documents/', $ROOT_APP.'/http/backend/config/legal-documents/index.php')
    ->permit(['admin']);
```

Questo permette di:

- mantenere i link vecchi
- pulire gradualmente i path
- avere finalmente nomi route riusabili con `__r(...)`

## Esempi concreti di migrazione

## Esempio 1: lista utenti backend

### Prima

File legacy:

- `app/build/src/backend/app/config/user/list.php`

```php
<?php

$BACKEND = true;
$PRIVATE = true;
$PERMIT = [];

$ROOT = $_SERVER['DOCUMENT_ROOT'];
require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

require_once "set-up.php";
require_once $ROOT_APP."/html/backend/list.php";
```

### Dopo

Handler:

- `app/http/backend/config/user/list.php`

```php
<?php

use Wonder\Backend\Support\LegacyTablePage;
use Wonder\View\View;

$page = LegacyTablePage::make($ROOT_APP.'/build/src/backend/app/config/user/set-up.php');

View::make($ROOT_APP.'/view/pages/backend/shared/table.php', [
    'TITLE' => 'Utenti backend',
    'TABLE_HTML' => $page['table_html'],
])->render();
```

View:

- `app/view/pages/backend/shared/table.php`

```php
<?php \Wonder\View\View::layout('backend.main'); ?>

<div class="row g-3">
    <wi-card class="col-12">
        <h3><?=htmlspecialchars((string) ($TITLE ?? ''), ENT_QUOTES, 'UTF-8')?></h3>
    </wi-card>

    <div class="col-12">
        <?=$TABLE_HTML?>
    </div>
</div>

<?php \Wonder\View\View::end(); ?>
```

Route:

```php
Route::get('/app/config/user/list/', $ROOT_APP.'/http/backend/config/user/list.php')
    ->name('config.user.list')
    ->permit([])
    ->mask('/app/config/user/list.php', 301);
```

Effetto:

- stessa logica tabellare
- stesso endpoint AJAX legacy
- layout nuovo
- route nominata
- compatibilita' URL storica

## Esempio 2: form documenti legali

### Prima

File legacy:

- `app/build/src/backend/app/config/legal-documents/index.php`

Problemi:

- pre-processing POST nello stesso file del markup
- include di `html/backend/index.php`
- layout completo inline

### Dopo

Handler:

- `app/http/backend/config/legal-documents/index.php`

```php
<?php

use Wonder\View\View;

$TITLE = 'Documento legale';
$VALUES = [];

require $ROOT_APP.'/build/src/backend/app/config/legal-documents/set-up.php';

if (isset($_POST['content_snapshot'])) {
    $_POST['content_hash'] = hash('sha256', $_POST['content_snapshot']);
}

if (isset($_POST['doc_type']) && (!isset($_POST['name']) || trim((string) $_POST['name']) === '')) {
    $_POST['name'] = ucwords(str_replace(['_', '-'], ' ', (string) $_POST['doc_type']));
}

require $ROOT_APP.'/html/backend/index.php';

View::make($ROOT_APP.'/view/pages/backend/config/legal-documents/index.php', [
    'TITLE' => $TITLE,
    'VALUES' => $VALUES ?? [],
    'REDIRECT' => $REDIRECT ?? '',
])->render();
```

View:

- `app/view/pages/backend/config/legal-documents/index.php`

```php
<?php \Wonder\View\View::layout('backend.main'); ?>

<form method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
    <div class="row g-3">
        <wi-card class="col-12">
            <h3>
                <a href="<?=$REDIRECT?>" type="button" class="text-dark">
                    <i class="bi bi-arrow-left-short"></i>
                </a>
                <?=$TITLE?>
            </h3>
        </wi-card>

        <div class="col-9">
            <div class="row g-3">
                <wi-card>
                    <div class="col-8">
                        <?=text('Nome', 'name', 'required'); ?>
                    </div>
                    <div class="col-4">
                        <?=select('Tipologia documento', 'doc_type', legalDocumentTypes(), null, 'required'); ?>
                    </div>
                </wi-card>
            </div>
        </div>

        <div class="col-3">
            <div class="row g-3">
                <wi-card class="col-12">
                    <div class="col-12">
                        <?=select('Stato', 'active', ['true' => 'Attivo', 'false' => 'Non attivo'], 'old', 'required'); ?>
                    </div>
                    <div class="col-12">
                        <?=submitAdd()?>
                    </div>
                </wi-card>
            </div>
        </div>
    </div>
</form>

<?php \Wonder\View\View::end(); ?>
```

Nota:

- `html/backend/index.php` puo' restare temporaneamente come bridge operativo
- la view nuova contiene solo markup
- il passo successivo sara' togliere anche `html/backend/index.php`

## Esempio 3: endpoint API backend migrato

Quando si migra un endpoint AJAX backend, il pattern deve essere questo:

Handler:

- `app/http/api/backend/change-boolean.php`

```php
<?php

use Wonder\Api\Endpoint;
use Wonder\Api\Handler;
use Wonder\Api\Response;

Handler::run('/api/backend/change-boolean/', 'POST', 'api_internal_user', function (Endpoint $call) {
    $table = (string) ($call->post['table'] ?? '');
    $column = (string) ($call->post['column'] ?? '');
    $id = (int) ($call->post['id'] ?? 0);

    if ($table === '' || $column === '' || $id <= 0) {
        return Response::json([
            'success' => false,
            'status' => 422,
            'response' => 'Parametri non validi.',
        ], 422);
    }

    $row = sqlSelect($table, ['id' => $id], 1)->row;
    $nextValue = (($row[$column] ?? 'false') === 'true') ? 'false' : 'true';

    sqlModify($table, [$column => $nextValue], 'id', $id);

    return Response::json([
        'success' => true,
        'status' => 200,
        'response' => 'Stato aggiornato.',
        'value' => $nextValue,
    ]);
});
```

Questo step va fatto solo quando la pagina chiamante non dipende piu' dal path legacy hardcoded.

## Compatibilita' legacy da mantenere durante tutta la migrazione

Va mantenuta esplicitamente questa compatibilita':

### 1. Globali legacy

Le view nuove devono continuare a funzionare con:

- `ALERT`
- `VALUES`
- `PATH`
- `PAGE`
- `USER`

Questo e' gia' compatibile con `View::make(...)` e `LegacyGlobals`.

### 2. Utility backend

I layout nuovi possono continuare a includere:

- `app/utility/backend/head.php`
- `app/utility/backend/body-start.php`
- `app/utility/backend/header.php`
- `app/utility/backend/footer.php`
- `app/utility/backend/body-end.php`

Non vanno riscritti subito.
Vanno solo spostati dietro un bootstrap piu' chiaro.

### 3. Helper input legacy

I form nuovi possono continuare a usare:

- `text()`
- `email()`
- `phone()`
- `password()`
- `select()`
- `submit()`

Condizione:

- l'handler deve passare `VALUES`
- l'handler deve passare `ALERT` quando serve

### 4. URL legacy `.php`

Per ogni modulo migrato servono:

- route canonical nuova
- mask legacy
- cleanup del path fisico pubblicato

## Rischi e come evitarli

### Rischio 1. La route nuova non prende traffico perche' esiste ancora il file fisico pubblicato

Motivo:

- `.htaccess` passa al router solo se il file o la directory non esistono

Mitigazione:

- cleanup progressivo in `app/build/cli/update.php`
- lista centrale dei moduli migrati in `app/config/backend/migrated.php`

### Rischio 2. Le nuove view perdono dati nei form

Motivo:

- gli helper backend leggono `global $VALUES`

Mitigazione:

- passare sempre `VALUES`
- passare `_POST` solo dove serve compatibilita' ulteriore

### Rischio 3. I toast/alert smettono di comparire

Motivo:

- `body-end.php` usa ancora `alert()`

Mitigazione:

- continuare a passare `ALERT`
- non rimuovere `body-end.php` finche' non esiste un meccanismo sostitutivo

### Rischio 4. Le tabelle backend si rompono dopo la migrazione HTML

Motivo:

- dipendono ancora da endpoint AJAX legacy e da `Path::apiDT`

Mitigazione:

- migrare prima il guscio HTML delle liste
- lasciare invariato `Path::apiDT` finche' non si migra anche l'AJAX

### Rischio 5. Il refactor diventa un meta-framework troppo astratto

Mitigazione:

- massimo due bridge generici:
  - `Bootstrap`
  - `LegacyTablePage`
- tutto il resto handler + view espliciti
- nessuna gerarchia controller pesante

## Piano operativo consigliato

### Step 1

- creare `Bootstrap`
- incapsulare `app/utility/backend/set-up.php`
- creare `LegacyTablePage`
- introdurre lista moduli migrati

### Step 2

- migrare tutte le pagine `list.php`
- aggiungere route canonical + mask legacy
- eliminare i path fisici pubblicati dei moduli migrati

### Step 3

- migrare `view.php` e `download.php`

### Step 4

- migrare i form principali:
  - `legal-documents`
  - `user`
  - `api-users`
  - `media/*`

### Step 5

- migrare gli endpoint `app/api/backend/*` nel router API
- aggiornare `Path`
- aggiornare `Backend\Table\Field`

## Decisione finale

La soluzione da applicare a questo progetto e':

- consolidare il backend sul sistema gia' presente `Route -> handler -> View`
- non introdurre Blade
- introdurre un bootstrap backend dedicato ma minimale
- usare bridge solo dove riducono davvero il costo di migrazione
- migrare prima le liste, poi detail/download, poi i form, infine l'AJAX

E' la strada piu' semplice, sostenibile e compatibile con il codice esistente.

Non richiede un rewrite totale.
Permette di rilasciare modulo per modulo.
Riduce da subito la duplicazione.
Mantiene operativo il backend durante tutta la transizione.
