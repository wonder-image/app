---
icon: table-columns
---

# TableColumn e tableLayoutSchema

## TableColumn

`TableColumn` (`class/App/ResourceSchema/TableColumn.php`) estende
`Wonder\Elements\Table\Column`. Si parte da `TableColumn::key('nome_colonna')`.

### Tipi di colonna

Impostano il tipo di resa (`setType`):

| Metodo | Resa |
|---|---|
| `text()` | testo semplice |
| `date()` / `datetime()` | data / data+ora |
| `phone()` | numero di telefono |
| `price()` | prezzo |
| `badge()` | badge colorato |
| `status()` | indicatore di stato |
| `user()` / `userAvatar()` / `userName()` | utente / avatar / nome |
| `icon()` | icona |
| `image()` | immagine/thumbnail |
| `button()` | cella con bottoni (usata con `actions()`) |

### Azioni

```php
TableColumn::key('actions')->button()->actions(['edit', 'delete']);
```

- `actions(array $actions)` — abilita più azioni in un colpo. Accetta una lista
  (`['edit', 'delete']`) o una mappa (`['edit' => true, 'delete' => false]`).
- `action(string $action, bool $enabled = true)` — abilita/disabilita una
  singola azione.

### Link

```php
TableColumn::key('name')->text()->link('edit');
```

`link($link)` rende la cella cliccabile. Nota: `link('edit')` viene tradotto
internamente in `'modify'` (la route di modifica). Puoi passare anche altri
target (`'view'`, `'mailto'`, `'tel'`, …).

### Metodi ereditati dalla base `Column`

| Metodo | Cosa fa |
|---|---|
| `label($s)` | intestazione colonna |
| `class($s)` | classi CSS aggiuntive |
| `size($s)` | larghezza: `auto`, `little`, `medium`, `big` |
| `hiddenDevice($s)` | nasconde su `mobile`/`tablet`/`desktop` |
| `sortable($b)` | colonna ordinabile |
| `function($name, $parameter = 'id', $return = null)` | valore calcolato da una funzione |
| `callback($cb)` | callback di rendering |
| `link($link)` | cella linkata |

Dettagli su `size`, `hiddenDevice`, `function` in
[Opzioni colonna](opzioni-colonna.md).

## tableLayoutSchema

`TableLayoutSchema` (`class/App/ResourceSchema/TableLayoutSchema.php`) definisce
la cornice attorno alla tabella. Si costruisce con
`TableLayoutSchema::for(static::class)`.

### Metodi

| Metodo | Cosa fa |
|---|---|
| `title($enabled = true, $text = null)` | blocco titolo pagina |
| `hideTitle()` | nasconde il titolo |
| `results($enabled = true)` | riga conteggio risultati |
| `buttonAdd($enabled = true, $label = null)` | CTA "Aggiungi" in alto a destra |
| `hideButtonAdd()` | nasconde la CTA |
| `filterSearch($enabled = true)` | casella di ricerca |
| `filterLimit($enabled = true)` | selettore "righe per pagina" |
| `filters($search = true, $limit = true)` | scorciatoia per i due sopra |
| `searchFields(array $fields)` | colonne interrogate dalla ricerca |
| `filterCustom(...)` | filtro custom |
| `filterRadio($label, $column, $options, $search = false, $value = null)` | filtro radio |
| `cleanHeader()` | header pulito |
| `download($formats = true, $label = null)` | export (CSV/…) |
| `downloadColumns(array $columns)` | colonne incluse nell'export |
| `downloadFileName(string $filename)` | nome file export |

### Default

Costruita con `for(...)` la cornice parte con titolo attivo, risultati attivi,
bottone "Aggiungi" attivo, ricerca + limite attivi. Override solo ciò che
differisce.

```php
public static function tableLayoutSchema(): TableLayoutSchema
{
    return TableLayoutSchema::for(static::class)
        ->title('Lista '.static::pluralLabel())
        ->buttonAdd('Aggiungi '.static::label())
        ->filters()
        ->searchFields(['name', 'email'])
        ->download(['csv'])
        ->downloadColumns(['id', 'name', 'email']);
}
```

L'export della cornice si appoggia alla route `export` generata dalla Resource
(gated sul permesso `list`): vedi [Route e API](../risorse/route-e-api.md).

## Errori comuni

- **Bottoni azione che non fanno nulla** → manca `->button()` prima di
  `->actions([...])`.
- **Ricerca che non filtra** → manca `searchFields([...])` nel layout.
- **Colonna calcolata vuota** → `function()` con `name` che non esiste o
  `parameter` sbagliato (default `'id'`).

## Checklist

- [ ] ogni colonna ha un tipo (`text()`, `badge()`, …)
- [ ] colonna `actions` con `->button()->actions([...])`
- [ ] `tableLayoutSchema()` con `searchFields` se la ricerca serve
- [ ] export configurato con `download()` + `downloadColumns()` se serve
