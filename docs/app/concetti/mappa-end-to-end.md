---
icon: diagram-project
---

# Mappa end-to-end

Questa è la pagina cardine: mostra come le 7 aree del framework si incastrano in
un'unica catena e come scorre un dato dall'inizio alla fine.

## La catena

```
Modulo → Risorsa → Form → Tabella → Database → Permessi → Componenti
```

1. **Modulo** — un pacchetto `wonder-image/<slug>` scoperto da
   `Module\Discovery`, validato da `ManifestValidator`, registrato da
   `Module\Registry`. Espone Model, Resource, route, traduzioni e permessi.
   Abilitato dal sito in `custom/config/modules.php`.
   → [Moduli](moduli/README.md)
2. **Risorsa** (`Resource`) — lega un `Model` e dichiara `formSchema()`,
   `tableSchema()`, `permissionSchema()`, `navigationSchema()`, `apiSchema()`.
   `ResourceRouteRegistrar` genera le route CRUD backend e API.
   → [Definire una Resource](risorse/resource.md)
3. **Form** — `formSchema()` ritorna `FormInput` (estende `FormField`). Reso da
   `FormField::render($theme)` via `FormFieldElementFactory` → componente → tema
   `Wonder` (frontend) o `Bootstrap` (backend).
   → [Form](form/README.md)
4. **Tabella** — `tableSchema()` ritorna `TableColumn`; `tableLayoutSchema()`
   definisce titolo, filtri, bottone aggiungi. `ResourceTableRenderer` produce
   la resa concreta.
   → [Render delle tabelle](tabelle/README.md)
5. **Database** — `Model::dataSchema()` (validazione + persistenza) e
   `Model::tableSchema()` (DDL via `sqlColumnsFromDataSchema()`). Soft-delete con
   `$defaultCondition`.
   → [Model e Database](risorse/database.md)
6. **Permessi** — `permissionSchema()` indica le authority per ogni azione;
   `ResourceRouteRegistrar` applica `Route::permit(...)`. Le chiavi vivono nel
   builder `app/config/app/permission.php` (+ merge moduli + `custom`).
   → [Builder permessi](utenti/permessi.md)
7. **Componenti** — Card, Container, Alert, Button, Badge, Dropdown (`class/Elements/Components/*`)
   compongono i layout di form (`formLayoutSchema()`) e di tabella.
   → [Componenti UI](componenti/README.md)

## Esempio minimo che attraversa tutta la catena

Un Model `Project` e la sua Resource bastano a generare tabella, form, API e
route, con permessi. Versioni complete in
[Model e Database](risorse/database.md) e [Definire una Resource](risorse/resource.md).

```php
// app/Models/Project.php
namespace App\Models;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class Project extends Model
{
    public static string $table  = 'projects';
    public static string $folder = 'projects';

    public static function tableSchema(): array
    {
        return [
            Column::key('position')->int(),
            ...static::sqlColumnsFromDataSchema(['slug', 'name', 'visible']),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('slug')->text()->slug(),
            Field::key('name')->text()->sanitizeFirst()->required(),
            Field::key('visible')->text(),
        ];
    }
}
```

```php
// app/Resources/ProjectResource.php
namespace App\Resources;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\FormInput;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;

final class ProjectResource extends Resource
{
    public static string $model = \App\Models\Project::class;

    public static function path(): string { return 'projects'; }

    public static function formSchema(): array
    {
        return [
            FormInput::key('name')->text()->required(),
            FormInput::key('visible')->select(['true' => 'Visibile', 'false' => 'Nascosto'])->value('true'),
        ];
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('name')->text()->link('edit'),
            TableColumn::key('visible')->badge(),
            TableColumn::key('actions')->button()->actions(['edit', 'delete']),
        ];
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backendCrud(['admin', 'administrator'])
            ->apiCrud(['admin', 'administrator']);
    }
}
```

Dopo `composer dump-autoload` + `php forge update --local`, il backend ha la
lista `/backend/projects/`, il form di creazione/modifica e le API
`/api/projects/`, tutte gated su `admin`/`administrator`.

## I 4 flussi

### 1. Creazione di un dato

```
Form backend (POST)
  → Resource::mutateRequestValues($values, 'store')   // es. genera slug
  → Model::validate($values)                           // regole dei Field
  → Model::prepare($values)                            // sanitize/slug/file/json
  → INSERT (Model::create)
  → Resource::afterStore($result, $values)             // hook post-insert
  → sync repeater relazionati (se presenti)
```

Se `validate()` fallisce, il flusso si ferma e il form torna con gli errori.
File coinvolti: `class/App/Resource.php`, `class/App/Model.php`.

### 2. Modifica di un record

```
Load record per id
  → Resource::mutateFormValues($values, 'edit')        // pre-fill
  → render form (valori esistenti)
  → POST → mutateRequestValues($values, 'update', oldValues)
  → Model::validate → Model::prepare → UPDATE (Model::update)
  → Resource::afterUpdate($id, $result, $values)
  → syncRepeaterRelations($id, ...)                    // righe correlate
```

Il soft-delete è una `update()` che imposta `deleted = 'true'`: non esiste un
metodo `softDelete()` sul Model.

### 3. Visualizzazione di una tabella

```
GET /backend/<slug>/
  → Resource::querySchema()        // condition + limit + order
  → query con $condition (esclude deleted='false')
  → Resource::tableSchema()        // colonne TableColumn
  → ResourceTableRenderer          // converte in resa concreta
  → tableLayoutSchema()            // titolo, filtri, bottone aggiungi
```

File: `class/Backend/Support/ResourceTableRenderer.php`. Dettagli in
[Render delle tabelle](tabelle/README.md).

### 4. Accesso negato per permessi mancanti

```
Route generata con ->permit(['admin','administrator'])
  → richiesta da utente con authority diversa (es. 'client')
  → il gate fallisce → risposta 403
```

Dove si configura: `permissionSchema()` della Resource per le azioni CRUD, e il
builder `app/config/app/permission.php` per l'esistenza delle chiavi authority.
Come si testa: accedere al backend con un utente privo dell'authority richiesta
e verificare il 403 sulla route. Dettagli in
[Builder permessi](utenti/permessi.md) e
[Gestione utenti](utenti/gestione-utenti.md).

## Dove andare adesso

- Costruire i dati: [Model e Database](risorse/database.md)
- Esporli come CRUD: [Definire una Resource](risorse/resource.md)
- Form e input: [Form](form/README.md)
- Liste: [Render delle tabelle](tabelle/README.md)
- Accessi: [Utenti e Permessi](utenti/README.md)
