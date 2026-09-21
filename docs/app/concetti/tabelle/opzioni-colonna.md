---
icon: sliders
---

# Opzioni colonna (size, hiddenDevice, format)

Opzioni comuni a tutte le colonne, ereditate da
`class/Elements/Table/Column.php`.

## `size` — larghezza colonna

```php
TableColumn::key('visible')->badge()->size('little');
```

Valori ammessi (validati: altrimenti `Exception`):

| Valore | Larghezza |
|---|---|
| `auto` (default se non impostato) | automatica |
| `little` | 30px |
| `medium` | 120px |
| `big` | 180px |

## `hiddenDevice` — visibilità responsive

```php
TableColumn::key('updated_at')->date()->hiddenDevice('mobile');
```

Valori ammessi: `mobile`, `tablet`, `desktop` (altrimenti `Exception`).

{% hint style="warning" %}
**Il valore indica il device su cui la colonna viene NASCOSTA, non quello su cui
è visibile.** `hiddenDevice('mobile')` → la colonna **sparisce su mobile** (il
runtime applica la classe `not-mobile`). Stessa logica per `tablet` e `desktop`.
Il nome del metodo (`hiddenDevice`) è coerente con questo: "device nascosto".
{% endhint %}

| Chiamata | Effetto |
|---|---|
| `hiddenDevice('mobile')` | nasconde su mobile |
| `hiddenDevice('tablet')` | nasconde su tablet |
| `hiddenDevice('desktop')` | nasconde su desktop |

## `function` — valore calcolato

```php
TableColumn::key('service')->badge()->function('mailService', 'service', 'automaticResize');
```

Firma: `function($name, $parameter = 'id', $return = null)`. Esegue una funzione
di formattazione: `name` è il nome della funzione, `parameter` il campo passato
(default `'id'`), `return` un eventuale valore atteso. Utile per badge di stato,
formattazioni custom, valori derivati.

> **Deprecato per i booleani:** `function('active'|'visible'|'evidence', ...)`
> è rimappato internamente sui badge booleani (vedi
> [TableColumn](tablecolumn.md#badge-booleani)) e continua a funzionare, ma i
> nuovi schema devono usare `activeBadge()` / `visibleBadge()` /
> `evidenceBadge()`.
>
> **Whitelist:** per gli altri nomi, la funzione viene eseguita solo se
> dichiarata in uno schema registrato lato server o consentita via
> `Wonder\Backend\Table\ColumnFunctionRegistry::allow('nomeFunzione')`
> (necessario per le pagine legacy con funzioni custom). Nomi non dichiarati
> producono cella vuota.

## `formatter` — cella calcolata in PHP (riga intera)

```php
TableColumn::key('duration')->text()->formatter(fn(array $row): string =>
    Presentation::number($row['average_ms'] ?? null, 1000)
    .'<div class="small text-body-secondary">Max '.Presentation::number($row['maximum_ms'] ?? null, 1000).'</div>'
);
```

Firma: `formatter(string|\Closure $formatter)`. La closure riceve **l'intera
riga** e ritorna l'HTML della cella: utile quando il valore dipende da più
campi (medie con "Max… · Tot…", valori derivati). La formattazione resta in
**PHP** (es. `Presentation::number`), non in JavaScript.

- **Auto-registrazione + whitelist:** la closure dichiarata in `tableSchema()`
  viene registrata in `Wonder\Backend\Table\ColumnFormatterRegistry` sotto
  `{slug}.{colonna}` in ogni request (rendering **e** endpoint SSP). Come per
  `function`, un nome non registrato produce cella vuota — mai esecuzione
  arbitraria dal POST di `list-table`.
- **Differenza da `function`:** `function` esegue un formatter per **nome** con
  un `parameter` singolo; `formatter` passa l'intera riga a una closure. Per gli
  aggregati (`GROUP BY`) è la via giusta — vedi
  [Appendice: Table legacy → Tabelle aggregate](legacy.md#tabelle-aggregate-group-by).

## `link` — cella cliccabile

```php
TableColumn::key('name')->text()->link('edit');
```

`link('edit')` viene tradotto in `'modify'` (route di modifica). Altri target:
`'view'`, `'mailto'`, `'tel'`. La cella diventa un link verso quella
destinazione per la riga corrente.

## `sortable` — ordinamento

```php
TableColumn::key('name')->text()->sortable();
```

Rende la colonna ordinabile dall'header.

## `class` — classi CSS

```php
TableColumn::key('name')->text()->class('text-uppercase');
```

Aggiunge classi CSS alla colonna.

## Esempio combinato

```php
public static function tableSchema(): array
{
    return [
        TableColumn::key('name')->text()->link('edit')->sortable(),
        TableColumn::key('email')->text()->hiddenDevice('mobile'),
        TableColumn::key('visible')->visibleBadge()->size('little'),
        TableColumn::key('created_at')->date()->size('medium')->hiddenDevice('tablet'),
        TableColumn::key('actions')->button()->actions(['edit', 'delete'])->size('little'),
    ];
}
```

## Errori comuni

- **`size`/`hiddenDevice` con valore non ammesso** → lancia `Exception`. Usa
  solo i valori delle tabelle sopra.
- **Aspettarsi che `hiddenDevice` mostri** → nasconde. È l'errore più frequente.
- **`function` senza il `parameter` giusto** → default `'id'`; passa il campo
  corretto se la funzione ne ha bisogno di un altro.

## Checklist

- [ ] `size` solo tra `auto`/`little`/`medium`/`big`
- [ ] `hiddenDevice` inteso come "nascondi su …"
- [ ] `function` con `name` e `parameter` corretti
- [ ] `link('edit')` per portare alla modifica
