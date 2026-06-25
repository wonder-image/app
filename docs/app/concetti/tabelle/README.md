---
icon: table
---

# Render delle tabelle

## Cos'è

La tabella di una lista backend si definisce sulla **Resource** con due metodi:

- `tableSchema(): array` — le **colonne** (lista di `TableColumn`);
- `tableLayoutSchema(): TableLayoutSchema` — la **cornice**: titolo, filtri,
  ricerca, bottone "Aggiungi", export.

Questa è l'**API corrente**. La vecchia classe `Wonder\Backend\Table\Table` è
ancora viva ma come **layer interno**, non come API da usare nei progetti nuovi.

## A cosa serve

Generare la lista paginata, ordinabile e filtrabile di una risorsa senza
scrivere HTML di tabella né query.

## Dove si trova nel codice

| Elemento | File | Ruolo |
|---|---|---|
| Colonna (API corrente) | `class/App/ResourceSchema/TableColumn.php` | cosa usi tu |
| Cornice | `class/App/ResourceSchema/TableLayoutSchema.php` | titolo/filtri/CTA |
| Base colonna | `class/Elements/Table/Column.php` | metodi comuni (`size`, `hiddenDevice`, …) |
| Renderer/ponte | `class/Backend/Support/ResourceTableRenderer.php` | converte `TableColumn` in resa concreta |
| Table legacy | `class/Backend/Table/Table.php` | runtime storico (DataTables) |

{% hint style="info" %}
**Esistono tre classi `Table.php`** nel codice:
`class/Backend/Table/Table.php` (runtime legacy/DataTables, usata internamente
dal renderer), `class/Elements/Table/Table.php` e `class/App/Table.php`. Per
costruire una lista **non** usi direttamente nessuna delle tre: usi
`Resource::tableSchema()` con `TableColumn`. Le `Table` restano dettaglio
interno. La legacy è documentata, per riferimento, nell'
[appendice](legacy.md).
{% endhint %}

## Esempio completo (copiabile)

```php
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;

public static function tableSchema(): array
{
    return [
        TableColumn::key('name')->text()->link('edit'),
        TableColumn::key('visible')->badge()->size('little'),
        TableColumn::key('updated_at')->date()->hiddenDevice('mobile'),
        TableColumn::key('actions')->button()->actions(['edit', 'delete']),
    ];
}

public static function tableLayoutSchema(): TableLayoutSchema
{
    return TableLayoutSchema::for(static::class)
        ->title('Lista '.static::pluralLabel())
        ->buttonAdd('Aggiungi '.static::label())
        ->filters()                       // search + selettore limite
        ->searchFields(['name']);         // colonne interrogate dalla ricerca
}
```

## Collegamenti con il resto

- I dati arrivano da `querySchema()` (condition/limit/order) della Resource:
  vedi [Definire una Resource](../risorse/resource.md).
- Le colonne `actions` (edit/delete) puntano alle route generate: vedi
  [Route e API](../risorse/route-e-api.md).
- Il soft-delete (`$defaultCondition`) esclude le righe cancellate: vedi
  [Model e Database](../risorse/database.md).

## Le pagine di questa sezione

- [TableColumn e tableLayoutSchema](tablecolumn.md) — tutti i metodi.
- [Opzioni colonna](opzioni-colonna.md) — `size`, `hiddenDevice`, `function`,
  `link`.
- [Appendice: Table legacy](legacy.md) — la vecchia API e il ponte
  `ResourceTableRenderer`.

## Errori comuni

- **Usare `Table::addColumn()` per una nuova risorsa** → è l'API legacy. Usa
  `tableSchema()` con `TableColumn`.
- **`hiddenDevice('mobile')` "non mostra su mobile"** → al contrario: **nasconde**
  su mobile. Vedi [Opzioni colonna](opzioni-colonna.md).

## Checklist

- [ ] `tableSchema()` con `TableColumn::key(...)`
- [ ] colonna `actions` con le azioni necessarie
- [ ] `tableLayoutSchema()` con titolo, filtri e `searchFields`
- [ ] nessuna costruzione manuale di `Table`
