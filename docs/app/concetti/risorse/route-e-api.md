---
icon: route
---

# Route e API generate

## Cos'è

`ResourceRouteRegistrar` (`class/App/ResourceRouteRegistrar.php`) scorre tutte le
Resource registrate ed emette **automaticamente** le route backend e API, ognuna
con il proprio gate di permesso. Non scrivi route a mano per il CRUD standard.

## A cosa serve

Da una Resource ottieni, senza codice extra: pagine backend, endpoint API REST,
export e gating dei permessi.

## Route backend generate

Nome route: `resource.<slug>.*`. Ogni azione viene emessa **solo se attiva** in
`pageSchema()->get('pages')` e con il permesso da `permissionSchema()`:

| Azione | Metodo + path | Gate (`permissionSchema` backend) |
|---|---|---|
| `list` | `GET /` | `list` |
| `create` | `GET /create/` | `create` |
| `store` | `POST /create/` | `store` |
| `view` | `GET /{id}/` | `view` |
| `edit` | `GET /{id}/edit/` | `edit` |
| `update` | `POST /{id}/edit/` | `update` |
| `delete` | `POST /{id}/delete/` | `delete` |
| `export` | `GET /export/{format}/` | **`list`** |

{% hint style="info" %}
La route **`export`** è generata in automatico (handler
`http/backend/resource/export.php`) ed è gated sullo stesso permesso di `list`:
chi può vedere la lista può esportarla. Non viene registrata quando `list` è
disabilitata o la Resource non ha una tabella: `NavigationOnlyResource` disabilita
`list` da sé, quindi le pagine non-CRUD dichiarano le proprie route (ad esempio in
`route.backend.php`) senza un secondo `/{resource}/` né un export.
{% endhint %}

## Default di `pageSchema()`

`PageSchema::for(static::class)` parte con questi default
(`class/App/ResourceSchema/PageSchema.php`):

| Pagina | Default |
|---|---|
| `list`, `create`, `store`, `edit`, `update`, `delete` | **attive** |
| `view` | **disattivata** |

{% hint style="warning" %}
`view` è **disabilitata di default**: la route `resource.<slug>.view` **non**
viene generata finché non la attivi esplicitamente. Per abilitarla:
`PageSchema::for(static::class)->enable('view')`.
{% endhint %}

Builder utili: `.enable($pages)`, `.disable($pages)`, `.only($pages)`,
`.layout($layout)`. Override `pageSchema()` solo per ciò che differisce dai
default.

```php
public static function pageSchema(): PageSchema
{
    return PageSchema::for(static::class)
        ->enable('view')           // attiva la pagina dettaglio
        ->disable('delete');       // niente eliminazione
}
```

## Route API generate

Nome route: `api.resource.<slug>.*`. Emesse solo se `apiSchema()->get('enabled')`
è vero, l'azione è attiva **e** `permissionSchema()` dichiara almeno
un'authority API per quell'azione:

| Azione | Metodo + path | Gate (`permissionSchema` api) |
|---|---|---|
| `index` | `GET /` | `index` |
| `store` | `POST /` | `store` |
| `show` | `GET /{id}/` | `show` |
| `update` | `PUT /{id}/` e `PATCH /{id}/` | `update` |
| `destroy` | `DELETE /{id}/` | `destroy` |

### `apiSchema()`

```php
use Wonder\App\ResourceSchema\ApiSchema;

public static function apiSchema(): ApiSchema
{
    return ApiSchema::for(static::class)
        ->fields('index', ['id', 'slug', 'name', 'visible'])  // proiezione campi
        ->fields('show',  ['id', 'slug', 'name', 'description', 'visible'])
        ->pagination(true, 25, 100);
}
```

Builder: `.enabled(bool)`, `.route($action, $enabled = true)`,
`.only([$routes])`, `.fields($action, [$fields])`, `.guard($guard)` (default
`api_internal_user`), `.allowPageToken(bool $allow = true)`,
`.pagination($enabled, $defaultLimit, $maxLimit)`.

### Chi può chiamare le route API

- **Permessi vuoti negano.** Un'azione senza authority in
  `permissionSchema()->api(...)` / `->apiCrud(...)` risponde `403` («Azione API
  non consentita per questa risorsa.») prima di leggere il bearer, e il
  registrar non la registra (scrive `api.resource.<slug>.<azione> non
  registrata` nel log). Il default di `PermissionSchema` è vuoto: una Resource
  che non dichiara permessi API non ha API. Gli endpoint non-Resource (MCP,
  task, update, alert) non cambiano.
- **`.guard($guard)`**: se la Resource lo dichiara, l'utente del bearer deve
  avere anche quell'authority, oltre a una di quelle dell'azione (`403`
  altrimenti). Senza `guard()` non si richiede nulla in più.
- **Accesso di pagina** (chiamate della lib senza bearer, con sessione e CSRF,
  o con un token di pagina `typ: page`): rifiutato con `401` sulle route
  Resource, salvo `.allowPageToken()`. Serve
  alle Resource chiamate dalla lib nel browser, per esempio un form pubblico
  che fa `store`:

```php
public static function permissionSchema(): PermissionSchema
{
    return PermissionSchema::for(static::class)->api('store', ['api_internal_user']);
}

public static function apiSchema(): ApiSchema
{
    return ApiSchema::for(static::class)->only(['store'])->allowPageToken();
}
```

{% hint style="warning" %}
`apiSchema()` modella **solo la superficie** (quali azioni e quali campi). **Chi
può chiamarle** dipende sempre da `permissionSchema()->apiCrud(...)` /
`->api(...)`. La proiezione `.fields(...)` è importante per non esporre colonne
sensibili.
{% endhint %}

## Mutazioni dall'elenco backend

Gli endpoint `api.backend.change.boolean` (e `.active`, `.visible`),
`api.backend.delete`, `api.backend.move`, `api.backend.file.delete`,
`api.backend.file.move` e `api.backend.authority` passano da
`Wonder\Backend\Table\TableAuthorization`:

- tabella con una Resource registrata: vale `permissionSchema()->backend(...)`
  di `update` (cambio booleano, ordinamento, file) o `delete`;
- tabella legacy senza Resource: solo `admin`;
- `file/delete` e `file/move`: tabella registrata in `Table` (schema letto con `Table::key(...)->schema()`), colonna con
  input di tipo file (`format.file`), `folder` normalizzato sotto `upload/`;
  altrimenti `422` generico, senza SQL;
- `authority`: solo tabella `user`, mai la propria riga, rotta
  `permit(['admin'])`.

Rifiuto: `403` «Operazione non consentita.».

## Slug e path

Il prefisso path viene da `Resource::path()` (o `Model::$folder` come fallback).
Lo slug usato nei nomi route è la versione "slugificata" del path (es.
`app/contact` → `app-contact`).

## Route custom oltre il CRUD

- `customBackendPages(): array` — elenca le azioni che la Resource gestisce da
  sé: il registrar **salta** quelle di default così puoi registrare la tua
  versione senza conflitti.
- `registerBackendRoutes($rootApp, $slug)` / `registerApiRoutes($rootApp, $slug)`
  — chiamate dal registrar dopo le route generate. Registra con
  `Wonder\Http\Route::get(...)` e gate `->permit($authorities)`.

## Errori comuni

- **"La pagina dettaglio non esiste"** → `view` è off di default; attivala con
  `pageSchema()->enable('view')`.
- **API non risponde** → `apiSchema()->enabled` falso, oppure azione non attiva,
  oppure permesso API mancante (la route non è registrata, `403`).
- **401 «Token di pagina non ammesso» o «Bearer token mancante!»** → la
  chiamata arriva dalla lib (sessione e CSRF o token di pagina): aggiungi
  `apiSchema()->allowPageToken()`.
- **403 sull'export** → l'utente non ha il permesso `list`.
- **Route in conflitto** → registri una route custom per un'azione senza averla
  messa in `customBackendPages()`.

## Checklist

- [ ] azioni backend attive coerenti col bisogno (`view` esplicito se serve)
- [ ] `apiSchema()` abilitato e con `.fields(...)` per le proiezioni
- [ ] permessi backend e API definiti in `permissionSchema()` (vuoto = negato)
- [ ] `allowPageToken()` solo se l'API è chiamata dalle pagine
- [ ] route custom dichiarate in `customBackendPages()` per evitare conflitti
