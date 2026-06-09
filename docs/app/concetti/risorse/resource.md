---
icon: cubes
---

# Definire una Resource

## Cos'è

Una **Resource** (`class/App/Resource.php`) espone un Model come CRUD backend e
API. Dichiara, tramite metodi statici, come appare il form, la lista, quali
permessi servono, la voce di menu e la superficie API.

## A cosa serve

Da una Resource registrata, `ResourceRouteRegistrar` genera automaticamente le
route backend e API e i relativi gate di permesso. Non scrivi controller né
route a mano.

## Dove si trova nel codice

- Base: `class/App/Resource.php`
- Schema: `class/App/ResourceSchema/*` (`FormInput`, `PermissionSchema`,
  `NavigationSchema`, `TableColumn`, `TableLayoutSchema`, `ApiSchema`,
  `PageSchema`)
- Esempi completi: `class/App/Resources/*` (es.
  `class/App/Resources/Css/CssAlertResource.php` per il layout del form)

## Scheletro minimo

```php
<?php

namespace App\Resources;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\FormInput;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;

final class ProjectResource extends Resource
{
    public static string $model = \App\Models\Project::class;

    public static string $orderColumn    = 'position';
    public static string $orderDirection = 'ASC';

    public static function path(): string { return 'projects'; }
    public static function icon(): string { return 'bi bi-folder'; }

    public static function textSchema(): array
    {
        return ['label' => 'progetto', 'plural_label' => 'progetti'];
    }

    public static function labelSchema(): array
    {
        return ['name' => 'Nome', 'visible' => 'Stato'];
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('name')->text()->required(),
            FormInput::key('visible')->select([
                'true'  => 'Visibile',
                'false' => 'Nascosto',
            ])->value('true')->required(),
        ];
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('name')->text()->link('edit'),
            TableColumn::key('visible')->badge()->size('little'),
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

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backendCrud(['admin', 'administrator'])
            ->apiCrud(['admin', 'administrator']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('Contenuti', 'content', 'bi-collection')
            ->title('Progetti')
            ->order(30)
            ->authority(['admin', 'administrator']);
    }
}
```

**Obbligatorio**: `public static string $model` con la FQN del Model. La base
verifica che esista ed estenda `Wonder\App\Model`.

## Proprietà e helper di base

| Elemento | Default | Significato |
|---|---|---|
| `$path` | `Model::$folder` | prefisso slug backend/API |
| `$condition` | `['deleted' => 'false']` | filtro WHERE della lista |
| `$limit`, `$orderColumn`, `$orderDirection` | — | default di listing |
| `path()`, `icon()`, `slug()` | — | override quando il valore è calcolato |
| `labelSchema()`, `textSchema()` | `[]` | etichette leggibili (i form senza `label()` la pescano da qui) |

## Gli schema della Resource

| Metodo | Cosa definisce | Pagina di dettaglio |
|---|---|---|
| `formSchema()` | input del form (lista di `FormInput`) | [Form](../form/README.md) |
| `formLayoutSchema()` | layout del form (Card/Container) | [Componenti](../componenti/README.md) |
| `tableSchema()` | colonne della lista (`TableColumn`) | [Tabelle](../tabelle/README.md) |
| `tableLayoutSchema()` | cornice della lista | [Tabelle](../tabelle/tablecolumn.md) |
| `permissionSchema()` | authority per ogni azione | [Permessi](../utenti/permessi.md) |
| `navigationSchema()` | voce di menu backend | qui sotto |
| `apiSchema()` | superficie API e proiezione campi | [Route e API](route-e-api.md) |
| `querySchema()` | condition/limit/order della lista | qui sotto |
| `pageSchema()` | quali pagine CRUD sono attive | [Route e API](route-e-api.md) |

### `permissionSchema()`

```php
public static function permissionSchema(): PermissionSchema
{
    return PermissionSchema::for(static::class)
        ->backendCrud(['admin', 'administrator'])
        ->apiCrud(['admin', 'administrator']);
}
```

Metodi: `.backend($actions, $authorities)`, `.api($actions, $authorities)`,
`.backendCrud($authorities)`, `.apiCrud($authorities)`,
`.allow($area, $actions, $authorities)`. Azioni backend valide: `list`,
`create`, `store`, `view`, `edit`, `update`, `delete`. Azioni API: `index`,
`store`, `show`, `update`, `destroy`. `$authorities = []` significa "nessun gate
extra oltre all'essere autenticati nell'area".

### `navigationSchema()`

```php
NavigationSchema::for(static::class)
    ->section('Avvisi', 'notices', 'bi-megaphone')
    ->title('Annunci')
    ->order(20)
    ->authority(['admin', 'administrator']);
```

Metodi: `.enabled(bool)`, `.section($title, $folder, $icon, $authority = [])`,
`.title()`, `.order()` (default 100), `.file()` (pagina linkata, default `list`),
`.authority([])`.

### `querySchema()`

Default:

```php
return [
    'condition' => static::$condition,
    'limit'     => static::$limit,
    'order'     => ['column' => static::$orderColumn, 'direction' => static::$orderDirection],
];
```

Override quando servono filtri/limiti/ordinamenti calcolati. Mantieni le chiavi
`condition` / `limit` / `order`: il codice di listing le legge direttamente.

## Hook del ciclo di vita

Override sulla Resource per iniettare comportamento attorno al CRUD (le
implementazioni base sono no-op):

| Hook | Quando |
|---|---|
| `mutateRequestValues($values, $action, $context = 'backend', $oldValues = null)` | prima di validazione/persistenza (es. genera slug) |
| `mutateFormValues($values, $mode, $context = 'backend')` | prima di rendere il form (pre-fill) |
| `findStoreExistingValues($requestValues, $context = 'backend')` | upsert idempotente: ritorna una riga esistente o `null` |
| `afterStore($result, $values = [])` | dopo l'insert |
| `afterUpdate($id, $result, $values = [])` | dopo l'update |
| `afterDelete($id, $result, $values = [])` | dopo il delete |

## Estendere oltre il CRUD

- `customBackendPages(): array` — elenco di azioni che la Resource gestisce da
  sé (`'create'`, `'edit'`, …): il registrar **non** genera quelle di default.
- `registerBackendRoutes($rootApp, $slug)` / `registerApiRoutes($rootApp, $slug)`
  — registra route extra con `Wonder\Http\Route::get(...)` e gating
  `->permit($authorities)`.

Quando la pagina non è legata a un singolo Model, usa invece
[CustomPageSchema](custom-page-schema.md).

## Errori comuni

- **`Input resource non trovato: ...`** in `formLayoutSchema()` → hai usato
  `static::getInput('campo')` per un campo non presente in `formSchema()`.
- **La route `.view` non esiste** → `PageSchema` ha `view` disattivata di
  default. Vedi [Route e API](route-e-api.md).
- **Permesso ignorato** → la chiave authority non esiste nel builder; vedi
  [Permessi](../utenti/permessi.md).
- **Schema chiamato su classe "non costruita"** → gli schema sono statici e
  chiamati lazy: usa solo `static::class`, `static::path()`, `static::modelClass()`.

## Checklist

- [ ] `$model` valido (estende `Wonder\App\Model`)
- [ ] `formSchema()` e `tableSchema()` coerenti col Model
- [ ] `permissionSchema()` con authority esistenti nel builder
- [ ] `navigationSchema()` se serve la voce di menu
- [ ] `composer dump-autoload` + `php forge update --local`
- [ ] route backend/API generate e gated correttamente
