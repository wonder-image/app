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
chi può vedere la lista può esportarla.
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
è vero e l'azione è attiva:

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
`api_internal_user`), `.pagination($enabled, $defaultLimit, $maxLimit)`.

{% hint style="warning" %}
`apiSchema()` modella **solo la superficie** (quali azioni e quali campi). **Chi
può chiamarle** dipende sempre da `permissionSchema()->apiCrud(...)` /
`->api(...)`. La proiezione `.fields(...)` è importante per non esporre colonne
sensibili.
{% endhint %}

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
  oppure permesso API mancante.
- **403 sull'export** → l'utente non ha il permesso `list`.
- **Route in conflitto** → registri una route custom per un'azione senza averla
  messa in `customBackendPages()`.

## Checklist

- [ ] azioni backend attive coerenti col bisogno (`view` esplicito se serve)
- [ ] `apiSchema()` abilitato e con `.fields(...)` per le proiezioni
- [ ] permessi backend e API definiti in `permissionSchema()`
- [ ] route custom dichiarate in `customBackendPages()` per evitare conflitti
