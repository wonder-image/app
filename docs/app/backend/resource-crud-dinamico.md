---
icon: grid-2
---

# CRUD dinamico con Model e Resource

Questo documento descrive lo stato attuale del sistema `Model + Resource`, i passaggi architetturali fatti e il modo corretto e minimale per usarlo nei nuovi moduli.

Manuale d'uso semplice:

- [Manuale Model e Resource](resource-manuale/README.md)
- [Quick Start](resource-manuale/quick-start.md)
- [Componenti](resource-manuale/componenti.md)
- [Resource e CustomPageSchema](resource-manuale/custom-page-schema.md)
- [Route e API](resource-manuale/route-e-api.md)

## Obiettivo

Registrare una risorsa una sola volta e ottenere automaticamente:

- backend CRUD HTML
- API CRUD JSON
- route dinamiche
- view condivise
- layout condivisi
- navigazione backend
- compatibilita' con il layer legacy dove serve ancora

## Stato attuale

Ad oggi sono attivi:

- `class/App/Model.php`
- `class/App/ModelRegistry.php`
- `class/App/Resource.php`
- `class/App/ResourceRegistry.php`
- `class/App/ResourceRouteRegistrar.php`
- `class/App/ResourceSchema/*`
- `class/Backend/Support/ResourcePageController.php`
- `class/Backend/Support/ResourcePagePresenter.php`
- `class/Backend/Support/ResourceFormLayoutRenderer.php`
- `class/Backend/Support/ResourceTableRenderer.php`
- `class/Backend/Support/BackendNavigation.php`
- `class/Api/Support/ResourceApiController.php`
- `class/Api/Support/ResourceApiPresenter.php`
- `app/http/backend/resource/index.php`
- `app/http/api/resource/index.php`
- `app/view/pages/backend/resource/list.php`
- `app/view/pages/backend/resource/form.php`
- `app/view/pages/backend/resource/show.php`
- `app/view/layout/backend/list_layout.php`
- `app/view/layout/backend/form_layout.php`
- `app/view/layout/backend/show_layout.php`
- `class/App/Models/CssFont.php`
- `class/App/Resources/CssFontResource.php`

Funzionalmente oggi il sistema offre:

- discovery automatica delle resource
- route backend dinamiche
- route API dinamiche
- CRUD backend condiviso
- CRUD API condiviso
- layout backend condivisi per `list`, `form`, `show`
- presenter separati backend/API
- integrazione del menu backend dal `ResourceRegistry`
- generatori `make:model` e `make:resource` aggiornati
- discovery automatica dei model
- bootstrap SQL dalle classi `Model`
- bootstrap prepare/upload dalle classi `Resource`
- primo layer repeater nativo per array JSON e relazioni 1:N

## Passaggi fatti

### 1. Rifattorizzazione del `Model`

`Model` e' diventato il layer dati.

Responsabilita':

- tabella SQL
- folder canonica del modulo
- icona del modulo
- schema SQL
- schema dati
- query, create, update, delete

Non conosce:

- route
- view
- pagine backend

### 2. Rifattorizzazione della `Resource`

`Resource` e' diventata il layer applicativo del modulo.

Responsabilita':

- riferimento al model
- testi
- label
- configurazione input
- configurazione colonne tabella
- layout backend del form
- layout backend della lista
- pagine backend abilitate
- endpoint API abilitati
- permessi backend e API
- metadata per il menu backend

### 3. Registry con discovery automatica

Le resource vengono scoperte automaticamente in:

- `class/App/Resources`
- `$ROOT/custom/class/Resources`

I file opzionali di override restano:

- `app/config/resource/resources.php`
- `$ROOT/custom/config/resource/resources.php`

Ordine di priorita':

1. discovery package
2. config package
3. discovery custom
4. config custom

### 4. Route dinamiche

Le route non vengono dichiarate dentro le resource.

Le route vengono generate da `ResourceRouteRegistrar` leggendo il `ResourceRegistry`.

Backend:

- path reale dal `Model::$folder`
- route names da `Resource::slug()`

API:

- path da slug interno
- route names da `Resource::slug()`

### 5. Controller e presenter condivisi

Backend:

- `ResourcePageController`
- `ResourcePagePresenter`

API:

- `ResourceApiController`
- `ResourceApiPresenter`

Questo evita file `http` per-entita' nei casi standard.

### 6. Layout backend condivisi

Sono stati introdotti:

- `backend.list`
- `backend.form`
- `backend.show`

Il layout form supporta `formLayoutSchema()` con renderer dedicato.

### 7. Compatibilita' con la legacy

La compatibilita' residua viene mantenuta dove serve davvero:

- le liste backend vengono renderizzate tramite `Wonder\Backend\Table\Table`
- i form backend continuano a usare i helper storici in `app/function/backend/input.php`
- `store/update` backend e API usano prima lo schema `prepareSchema()` della resource migrata
- il layer `Wonder\App\Table` legacy resta come fallback per i moduli non ancora migrati
- questo consente di mantenere il flusso upload basato su `formToArray()` senza dipendere piu' da `build/table` per i moduli nuovi

### 8. Navigazione backend

`BackendNavigation` legge il `ResourceRegistry` e integra automaticamente le voci nel menu backend.

### 9. Generatori aggiornati

Sono stati riallineati:

- `make:model`
- `make:resource`

Le classi generate usano gia' il contratto nuovo.

## Concetti base

### `Model`

Il `Model` descrive:

- dove vengono salvati i dati
- come e' fatta la tabella SQL
- come preparare e validare i dati

In forma sintetica:

- `Model::tableSchema()` definisce la struttura SQL della tabella
- `Model::dataSchema()` definisce come trattare i dati prima del salvataggio

`tableSchema()` resta esplicito. Quando serve ridurre duplicazione, puo' comporsi con:

- `static::sqlColumnsFromDataSchema([...])`

### `Resource`

La `Resource` descrive:

- come mostrare quei dati nel backend
- quali input usare
- quali colonne usare
- come impaginare il form
- come impaginare la lista
- quali route backend e API esporre

In forma sintetica:

- `Resource::formSchema()` definisce gli input del backend

## Contratto di `Model`

Esempio minimo:

```php
<?php

namespace Wonder\App\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class Contact extends Model
{
    public static string $table = 'contact';
    public static string $folder = 'app/contact';
    public static string $icon = 'bi bi-person';

    public static function tableSchema(): array
    {
        return [
            Column::key('name'),
            Column::key('surname'),
            Column::key('email'),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('name')->text()->required()->sanitizeFirst(),
            Field::key('surname')->text()->required()->sanitizeFirst(),
            Field::key('email')->text()->required(),
        ];
    }
}
```

Regole:

- `tableSchema()` definisce la struttura SQL della tabella
- `dataSchema()` definisce come trattare i dati prima del salvataggio
- `folder` e' il path canonico backend del modulo
- `icon` viene usata anche per il menu backend

## Contratto di `Resource`

Esempio minimo:

```php
<?php

namespace Wonder\App\Resources;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\FormInput;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Elements\Form\Form;
use Wonder\Elements\Components\Card;
use Wonder\App\Models\Contact;

final class ContactResource extends Resource
{
    public static string $model = Contact::class;

    public static function textSchema(): array
    {
        return [
            'label' => 'contatto',
            'plural_label' => 'contatti',
            'last' => 'ultimi',
            'all' => 'tutti',
            'article' => 'i',
            'full' => 'pieno',
            'empty' => 'vuoto',
            'this' => 'questo',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'name' => 'Nome',
            'surname' => 'Cognome',
            'email' => 'Email',
            'actions' => 'Azioni',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('name')->text()->required(),
            FormInput::key('surname')->text()->required(),
            FormInput::key('email')->email()->required(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                static::getInput('name')->columnSpan(1),
                static::getInput('surname')->columnSpan(1),
                static::getInput('email')->columnSpan(2),
            ])->columns(2)->columnSpan(2),
        ])->columns(2);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('name')->text()->link('edit'),
            TableColumn::key('surname')->text(),
            TableColumn::key('email')->text(),
            TableColumn::key('actions')->button()->actions(['edit', 'delete']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Lista '.static::pluralLabel())
            ->buttonAdd('Aggiungi '.static::label())
            ->filters();
    }
}
```

## Regole importanti

### `formSchema()` e `tableSchema()`

Devono restituire **array di configuratori per-campo**.

Corretto:

```php
public static function formSchema(): array
{
    return [
        FormInput::key('name')->text()->required(),
        FormInput::key('email')->email(),
    ];
}
```

Corretto:

```php
public static function tableSchema(): array
{
    return [
        TableColumn::key('name')->text()->link('edit'),
        TableColumn::key('actions')->button()->actions(['edit', 'delete']),
    ];
}
```

Non devono descrivere il layout pagina.

### `formLayoutSchema()`

Serve solo per la disposizione visuale degli input nel backend.

Usa:

- `Form`
- `Card`
- `static::getInput('key')`

### `tableLayoutSchema()`

Serve solo per la UI della lista backend:

- titolo
- bottone aggiungi
- filtri
- risultati

### `pageSchema()`, `apiSchema()`, `permissionSchema()`, `navigationSchema()`

Questi metodi devono restituire **oggetti schema**, non array grezzi.

Esempio:

```php
public static function apiSchema(): ApiSchema
{
    return static::api()
        ->route('index')
        ->route('store')
        ->route('show')
        ->route('update')
        ->route('destroy');
}
```

## Route generate

Per un model con:

```php
public static string $folder = 'app/css/font';
```

la resource genera:

### Backend

- `GET /backend/app/css/font/`
- `GET /backend/app/css/font/create/`
- `POST /backend/app/css/font/create/`
- `GET /backend/app/css/font/{id}/`
- `GET /backend/app/css/font/{id}/edit/`
- `POST /backend/app/css/font/{id}/edit/`
- `POST /backend/app/css/font/{id}/delete/`

### Route names backend

- `backend.resource.app-css-font.list`
- `backend.resource.app-css-font.create`
- `backend.resource.app-css-font.store`
- `backend.resource.app-css-font.view`
- `backend.resource.app-css-font.edit`
- `backend.resource.app-css-font.update`
- `backend.resource.app-css-font.delete`

### API

Lo slug interno diventa `app-css-font`, quindi le route API sono:

- `GET /api/resource/app-css-font/`
- `POST /api/resource/app-css-font/`
- `GET /api/resource/app-css-font/{id}/`
- `PUT /api/resource/app-css-font/{id}/`
- `PATCH /api/resource/app-css-font/{id}/`
- `DELETE /api/resource/app-css-font/{id}/`

### Route names API

- `api.resource.app-css-font.index`
- `api.resource.app-css-font.store`
- `api.resource.app-css-font.show`
- `api.resource.app-css-font.update`
- `api.resource.app-css-font.update.patch`
- `api.resource.app-css-font.destroy`

## Uso semplice

### 1. Creare il model

```bash
php forge make:model Contact
```

Poi compilare:

- `table`
- `folder`
- `icon`
- `tableSchema()`
- `dataSchema()`

### 2. Creare la resource

```bash
php forge make:resource Contact
```

Poi compilare:

- `textSchema()`
- `labelSchema()`
- `formSchema()`
- `formLayoutSchema()` se serve
- `tableSchema()`
- `tableLayoutSchema()`
- `pageSchema()`
- `apiSchema()`
- `permissionSchema()`
- `navigationSchema()`

### 3. Non registrare manualmente la resource salvo eccezioni

La resource viene scoperta automaticamente se si trova in:

- `class/App/Resources`
- `$ROOT/custom/class/Resources`

### 4. Aprire il backend

Se il model ha:

```php
public static string $folder = 'app/contact';
```

il modulo backend sara' disponibile in:

- `/backend/app/contact/`

### 5. Usare l'API

Se la resource e' `app/contact`, lo slug interno e' `app-contact`.

API:

- `/api/resource/app-contact/`

## Builder disponibili

### Input backend

Disponibili tramite `FormInput::key(...)`:

- `text()`
- `textGenerator()`
- `textDate()`
- `textDatetime()`
- `dateInput()`
- `dateRange()`
- `color()`
- `email()`
- `number()`
- `price()`
- `percentige()`
- `password()`
- `tel()`
- `phone()`
- `url()`
- `textarea()`
- `select()`
- `selectSearch()`
- `checkbox()`
- `inputFile()`
- `inputFileDragDrop()`

Helper utili:

- `required()`
- `label()`
- `value()`
- `options()`
- `columnSpan()`
- `error()`

### Colonne tabella

Disponibili tramite `TableColumn::key(...)`:

- `text()`
- `date()`
- `phone()`
- `price()`
- `badge()`
- `icon()`
- `image()`
- `button()`

Helper utili:

- `link('edit')`
- `action('edit')`
- `actions(['edit', 'delete'])`
- `function(...)`
- `value(...)`
- `size(...)`

Nota:

- `link('edit')` viene normalizzato internamente verso il naming legacy necessario al renderer tabella

## Note pratiche

### Backend list

La lista backend usa `Wonder\Backend\Table\Table`.

Questo e' voluto per riusare il renderer storico e ridurre il lavoro duplicato.

### Backend form

Il form backend usa ancora i helper in `app/function/backend/input.php`.

Questo e' voluto per continuare a sfruttare:

- tipologie input esistenti
- compatibilita' upload
- flusso storico `formToArray()`

### Upload

Per i moduli migrati lo schema di prepare/upload viene ora registrato automaticamente dalla resource.

Se il modulo non e' ancora migrato, `store/update` backend passano ancora da:

- `Table::key($table)->prepare(...)`

quindi la gestione upload continua a funzionare senza doverla riscrivere.

Per i moduli migrati, invece, il flusso corretto e':

- `Model::tableSchema()` -> schema SQL
- `Model::dataSchema()` -> base di validazione / prepare
- `Resource::formSchema()` -> metadata input e upload
- `Resource::prepareSchema()` -> bridge finale compatibile con `formToArray()`

### Discovery

`app/config/resource/resources.php` non e' piu' il punto principale di registrazione.

Resta solo come override opzionale.

## Risorsa pilota

La risorsa pilota completa e' oggi:

- `class/App/Models/CssFont.php`
- `class/App/Resources/CssFontResource.php`

Serve come riferimento reale per:

- path backend da `folder`
- lista backend dinamica
- form layout dinamico
- API dinamica
- integrazione col menu backend

## Prossimi miglioramenti naturali

- ampliare i DSL dei layout backend
- rifinire ancora il bridge con `Wonder\Backend\Table\Table`
- estendere la migrazione ad altre entita' legacy

## Roadmap residua

### Priorita' alta

- rimuovere i file `app/build/table/*.php` dei moduli gia' migrati
- rimuovere i file `app/build/src/backend/*` dei moduli gia' migrati
- lasciare `app/build/row/*` attivo finche' non verra' progettato il layer seed nuovo
- completare il passaggio di prepare/upload/normalizzazioni a `Model::dataSchema()`: il bridge centrale ora legge `dataSchema()` e gli override `legacyTableSchema()` nei model applicativi sono stati rimossi; resta da valutare se mantenere `legacyTableSchema()` solo come bridge del base `Model` o eliminarlo del tutto in un secondo step
- progettare un sostituto di `SortableInput` come componente repeater/collection nativo, capace di gestire sia array JSON sia righe destinate a tabelle relazionate, inclusi upload per-riga e prepare coerente

### Stato attuale della pulizia legacy

- `build/table/css.php` e' stato rimosso
- `build/src/backend/app/css/*` e' stato rimosso
- `build/table/media.php` e' stato rimosso
- `build/src/backend/app/media/{documents,images,icons,logos}` e' stato rimosso
- `build/table/{seo,analytics}.php` e' stato rimosso
- `build/src/backend/app/config/{seo,analytics}` e' stato rimosso
- `legal_documents` e' ora governato da [LegalDocument.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Models/Config/LegalDocument.php) e [LegalDocumentResource.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Resources/Config/LegalDocumentResource.php)
- `build/src/backend/app/config/legal-documents/*` e' stato rimosso
- `app/build/table/consent.php` mantiene solo le tabelle consenso non ancora migrate; `legal_documents` e' stato spostato nel `Model`
- `security` e' ora governato da [Security.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Models/Config/Security.php) e [SecurityResource.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Resources/Config/SecurityResource.php)
- `build/src/backend/app/config/credentials/index.php` e' stato rimosso
- il blocco `user` e' ora governato da model dedicati in [class/App/Models/User](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Models/User) e da resource dedicate in [class/App/Resources/User](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Resources/User)
- `build/table/user.php` e' stato rimosso
- `build/src/backend/app/config/{user,api-users}` e' stato rimosso
- `user` e `api-users` usano ora create/edit condivisi via [UserManagementPageController.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/Backend/Support/UserManagementPageController.php) e [manage.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/app/view/pages/backend/user/manage.php)
- `auth-users` e' ora governato da [AuthLog.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Models/User/AuthLog.php) e [AuthLogResource.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Resources/User/AuthLogResource.php)
- `build/src/backend/app/log/auth-users/*` e' stato rimosso
- il blocco `consent` e' ora governato da model dedicati in [class/App/Models/Consent](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Models/Consent) e da [ConsentEventResource.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Resources/Consent/ConsentEventResource.php)
- `build/table/consent.php` e' stato rimosso
- `build/src/backend/app/log/consent/*` e' stato rimosso
- `mail_log` e' ora governato da [MailLog.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Models/Log/MailLog.php) e [MailLogResource.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Resources/Log/MailLogResource.php)
- `build/table/mail.php` e' stato rimosso
- `build/src/backend/app/log/email/*` e' stato rimosso
- `configuration-file` e' stato migrato come resource speciale con model astratto, route custom e pagina dedicata in [configuration-file.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/app/http/backend/config/configuration-file.php)
- `sql-download` e' stato migrato a route/handler/view nuove in [sql-download.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/app/http/backend/config/sql-download.php)
- `corporate-data` e' stato migrato a route/handler/view nuove in [corporate-data.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/app/http/backend/config/corporate-data.php), con 4 model reali al posto di `build/table/society.php`
- il blocco `account` usa ora solo `app/http/backend/account/*` e `app/view/pages/backend/account/*`
- `build/src/backend/index.php` e `build/src/backend/home/index.php` sono stati rimossi
- `build/src/backend/app/config/configuration-file/index.php` e' stato rimosso
- `build/src/backend/account/*` e' stato rimosso
- le vecchie `list.php` legacy di `user`, `api-users`, `auth-users`, `consent`, `email` sono state rimosse
- `app/media/upload-massive` resta una utility legacy separata, ma prepara i file usando le `Resource`

Note tecniche recenti:
- [Model.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Model.php) supporta ora anche `tableOptions()` e `tablePseudos()` per opzioni tabella, indici composti, unique composte e primary composte
- [TableLayoutSchema.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/ResourceSchema/TableLayoutSchema.php) supporta `searchFields()` e `filterRadio()/filterCustom()` per i filtri lista backend
- i moduli nuovi devono ottenere schema SQL da `Model`
- i moduli nuovi devono ottenere schema prepare/upload da `Resource`
- il bridge runtime `formToArray()` ora deriva i format da `Model::dataSchema()` tramite [Model.php](/Users/andreamarinoni/Desktop/PROGETTI/template/app/class/App/Model.php); gli override `legacyTableSchema()` nei model applicativi sono stati eliminati
- `SortableInput` resta temporaneamente attivo per compatibilita' ma va sostituito con un layer piu' strutturato, soprattutto nei casi `variants/products`, `allowed_domains`, `allowed_ips` e upload multipli per riga
- il primo step del nuovo layer e' attivo: `FormInput::repeater()` sostituisce gia' `SortableInput` nei casi JSON semplici, a partire da `api-users` (`allowed_domains`, `allowed_ips`)
- il secondo step e' attivo: `RepeaterColumn` + `nested()` + `Wonder\App\Support\Repeater::rowsFromRequest()` coprono gia' righe multi-colonna, hidden/id e upload per riga
- il repeater supporta ora anche l'ordinamento lato UI con `->repeaterSortable()`, utile quando la relazione usa `positionKey()`
- il repeater supporta una conferma eliminazione con modale Bootstrap customizzabile da schema (`repeaterDeleteTitle/Text/CancelLabel/ConfirmLabel/ConfirmClass`)
- il terzo step e' avviato: `RepeaterRelation` + `Wonder\App\Support\Repeater::syncRelatedRows()` introducono il primo layer generico di persistenza 1:N, con supporto a update, insert, soft delete e position
- il preload del repeater e' ora automatico nel CRUD resource: in caso di errore ricostruisce le righe dal request, e in `edit` puo' caricare anche le righe figlie della relazione
- anche l'API resource standard e' ora allineata: le relation rows possono essere sincronizzate da payload strutturati e vengono restituite automaticamente in `show/index`
- il primo caso reale 1:N e' ora impostato su `corporate-data`: gli orari possono vivere nella nuova tabella `society_timetable`, con fallback al JSON legacy `society_address.timetable` finche' lo schema non viene aggiornato
- il CRUD backend/API standard ora esclude automaticamente i repeater relazionali dal payload principale e li sincronizza dopo `store/update`
- il primo uso reale del nuovo repeater e' gia' attivo in `corporate-data`, dove il blocco orari non usa piu' `SortableInput`
- `RepeaterRelation` puo' ora agganciarsi a `Model` o `Resource` del figlio, cosi' il sync usa automaticamente `dataSchema()` / `prepareSchema()` anche per upload e normalizzazioni delle righe correlate
