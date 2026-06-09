---
icon: clone
---

# Repeater

## Cos'è

Un **repeater** è un campo che permette di inserire **più righe** dello stesso
gruppo di input (es. una lista di step, di domini, di immagini). Si dichiara con
`->repeater([...])` su un `FormInput`, passando una o più `RepeaterColumn`.

## A cosa serve

Gestire dati ripetibili senza creare un form separato. Le righe possono essere
salvate in due modi:

- **JSON inline** sul record padre (nessuna relazione necessaria);
- **righe in una tabella correlata** (con `RepeaterRelation`).

## Dove si trova nel codice

- Dichiarazione: `FormField::repeater()` e `class/App/ResourceSchema/RepeaterColumn.php`
  (estende `FormField`, quindi dentro una riga hai tutto il DSL dei campi).
- Relazione: `RepeaterRelation`.
- Sync: helper su `Resource` (`syncRepeaterRelations`, …) e
  `class/App/Support/Repeater.php`.

## Esempio: repeater inline (JSON)

```php
use Wonder\App\ResourceSchema\FormInput;
use Wonder\App\ResourceSchema\RepeaterColumn;

FormInput::key('allowed_domains')
    ->repeater([
        RepeaterColumn::key('allowed_domains')
            ->text()
            ->label('Dominio')
            ->columnSpan(11),
    ])
    ->repeaterSortable()
    ->repeaterAddLabel('Aggiungi dominio')
    ->label('Domini');
```

## Esempio: repeater su tabella correlata

Quando le righe vivono in un'altra tabella, attacca un `RepeaterRelation` **sul
`FormInput` di livello superiore** (quello che possiede il `->repeater([...])`):

```php
use Wonder\App\ResourceSchema\FormInput;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\App\ResourceSchema\RepeaterRelation;

FormInput::key('steps')
    ->repeater([
        RepeaterColumn::key('title')->text()->required(),
        RepeaterColumn::key('position')->number()->columnSpan(2),
    ])
    ->repeaterSortable()
    ->relation(
        RepeaterRelation::make($table = 'project_steps', $parentKey = 'project_id')
            ->positionKey('position')
            ->softDelete(true, 'deleted')
            ->resource(\App\Resources\ProjectStepResource::class)
    );
```

{% hint style="warning" %}
`->relation(...)` esiste su `FormField` (quindi anche su `RepeaterColumn`), ma il
layer Resource lo legge **solo dal campo repeater di primo livello** in
`formSchema()`. Attaccarlo a una `RepeaterColumn` figlia è un **no-op**.
{% endhint %}

## Modificatori del repeater

Sul `FormInput` che chiama `->repeater(...)`:

`repeaterSortable($b = true)`, `repeaterAddLabel($s)`, `repeaterButtonClass($s)`,
`repeaterDeleteTitle($s)`, `repeaterDeleteText($s)`,
`repeaterDeleteCancelLabel($s)`, `repeaterDeleteConfirmLabel($s)`,
`repeaterDeleteConfirmClass($s)`, `relation($relation)`.

## RepeaterRelation

`RepeaterRelation::make($table, $parentKey)` accetta:

- coppia tabella + chiave del padre, oppure
- `->resource($resourceClass)` per derivare tabella, schema di prepare e folder
  dalla Resource correlata;
- `->positionKey('position')`, `->softDelete(true, 'deleted')`.

Gli helper sulla Resource padre gestiscono lettura/scrittura:
`syncRepeaterRelations($parentId, $post, $files, $action, $context)`,
`appendRepeaterRelationsToItem($item)`,
`appendRepeaterRelationsToCollection($items)`,
`hydrateRepeaterFormValues($values, $parentId, $post, $files)`. Per mutare il
payload di una riga prima del salvataggio:
`prepareRepeaterRelationRow(...)`.

## Collegamenti con il resto

- Ogni `RepeaterColumn` è un `FormField`: vale tutto il DSL di
  [FormField](form-field.md).
- Le righe correlate fanno riferimento al record padre: il padre deve esistere
  (o `$parentId` deve essere risolto) **prima** del sync.

## Errori comuni

- **Righe non salvate** → `syncRepeaterRelations($parentId, ...)` con `$parentId`
  mancante o zero è un no-op: le righe vengono silenziosamente scartate.
- **`relation()` su una colonna figlia** → ignorato; va sul `FormInput`
  top-level.
- **Ordinamento non persistito** → manca `->positionKey(...)` o
  `->repeaterSortable()`.

## Checklist

- [ ] `->repeater([...])` con `RepeaterColumn::key(...)` per ogni colonna
- [ ] per righe correlate: `->relation(RepeaterRelation::make(...))` sul campo
      top-level
- [ ] padre creato/risolto prima di `syncRepeaterRelations()`
- [ ] `positionKey` + `repeaterSortable()` se l'ordine conta
