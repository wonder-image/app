---
icon: clone
---

# Repeater

## Cos'è

Un **repeater** è un campo che permette di inserire **più righe** dello stesso
gruppo di input (es. una lista di step, di domini, di immagini). Si dichiara con
`->repeater([...])` su un `FormField`, passando una o più `RepeaterColumn`.

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
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\RepeaterColumn;

FormField::key('allowed_domains')
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
`FormField` di livello superiore** (quello che possiede il `->repeater([...])`):

```php
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\App\ResourceSchema\RepeaterRelation;

FormField::key('steps')
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

Sul `FormField` che chiama `->repeater(...)`:

`repeaterSortable($b = true)`, `repeaterAddLabel($s)`, `repeaterButtonClass($s)`,
`repeaterDeleteTitle($s)`, `repeaterDeleteText($s)`,
`repeaterDeleteCancelLabel($s)`, `repeaterDeleteConfirmLabel($s)`,
`repeaterDeleteConfirmClass($s)`, `relation($relation)`,
`repeaterGroupBy(...$colonne)`, `repeaterGroupCommand($colonna, $etichetta)`,
`repeaterGroupCollapsed($b = true)`, `repeaterGroupCountLabel($sing, $plur)`,
`repeaterGroupFixed($colonna)`, `repeaterAddButton($b = true)`,
`repeaterStartEmpty($b = true)`.

## Righe raggruppate

Con molte righe la griglia diventa un muro. Dichiarando una o più colonne
raggruppabili, sopra il repeater compare un selettore **"Raggruppa per"**: le
righe si dividono in blocchi richiudibili con la loro testata, e la testata può
portare una casella che scrive su tutte le righe del gruppo.

```php
FormField::key('products')
    ->repeater([
        RepeaterColumn::key('product_variant_id')->select($colori)->label('Colore')->columnSpan(2),
        RepeaterColumn::key('name')->text()->label('Versione')->columnSpan(2),
        RepeaterColumn::key('price')->number()->decimal(2)->label('Prezzo')->columnSpan(2),
    ])
    ->repeaterGroupBy('product_variant_id')
    ->repeaterGroupCommand('price', 'Prezzo del gruppo')
    ->repeaterGroupCountLabel('versione', 'versioni');
```

**È una vista, non una struttura.** Le testate si aggiungono in fondo al
contenitore e l'ordine visivo lo fa `order` di flexbox: il DOM delle righe non
si sposta mai, quindi posting, `positionKey` e riordino restano quelli di
sempre, e tornare a "Nessuno" è azzerare due proprietà.

**La casella sulla testata è un comando, non un dato.** Non viene postata:
scrive il valore negli input delle righe del gruppo, sotto gli occhi di chi
guarda, e da lì in poi è una modifica come le altre. Se la colonna di
destinazione è un campo numerico del pannello, il valore passa dall'API di
AutoNumeric — assegnargli `value` lascerebbe il widget con lo stato vecchio, e
al salvataggio riscriverebbe lui.

Quello che v1 **non** fa:

- raggruppare per più colonne insieme: una per volta;
- raggruppare per una colonna senza valori enumerabili — serve una `select` (o
  una colonna di testo, che si etichetta da sé);
- convivere con il riordino: con un gruppo attivo le frecce spariscono, perché
  "sposta su" dentro un gruppo non vuol dire niente. Tornano con "Nessuno".

Il selettore compare solo se le righe sono più di una, e la scelta si ricorda
in `localStorage` sul **nome del campo** (l'id del repeater lo genera il
render, cambia a ogni caricamento).

### Quando i gruppi sono il significato

`repeaterGroupFixed('colonna')` raggruppa **sempre** per quella colonna: niente
selettore — non c'è niente da scegliere — e i gruppi ci sono anche quando la
riga è una sola. Si usa quando senza i gruppi non si capisce cosa si sta
leggendo: le taglie di un colore, le righe di un documento.

Con la colonna decisa il riordino a mano si spegne da sé (`sortable` torna
falso), così la colonna dei bottoni non resta larga e vuota, e la memoria in
`localStorage` non si scrive: ricorderebbe una scelta che nessuno ha fatto.

Una colonna fissa che non esiste fra quelle dichiarate non raggruppa niente,
come già succede alle colonne di `repeaterGroupBy()`.

## Righe che nascono da fuori

Un repeater si compila a mano, ma può anche ricevere le righe da altro codice
della pagina — una spunta, un calcolo, un elenco che arriva da un'API:

```php
FormField::key('products')
    ->repeater([...])
    ->repeaterGroupFixed('variant')
    ->repeaterAddButton(false)   // le righe non si aggiungono a mano
    ->repeaterStartEmpty();      // e senza righe non se ne mostra una vuota
```

- `repeaterAddButton(false)` toglie il bottone «Aggiungi». Con il bottone via,
  anche l'**ultima** riga si può eliminare: la guardia che la svuota invece di
  toglierla esiste perché se ne possa aggiungere un'altra.
- `repeaterStartEmpty()` non stampa la riga vuota di cortesia. Senza, quella
  riga viene postata comunque e a valle diventa un record senza niente dentro.
- `window.wiRepeaterAddRow(contenitoreId, templateId, chiave)` aggiunge una
  riga e **la restituisce**, così chi l'ha chiesta la riempie. La chiave è
  facoltativa: passandola, i campi si chiamano `campo[chiave][colonna]` — utile
  quando la riga corrisponde a qualcosa che ha già un nome altrove. Viene
  ripulita di tutto ciò che non è lettera, cifra, trattino o underscore, e una
  chiave già presente non crea una seconda riga (nel posting si fonderebbero).
- Il contenitore esterno porta `data-wi-repeater="<nome del campo>"`: è la
  maniglia per trovarlo senza dipendere dall'id, che cambia a ogni render.

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
- **`relation()` su una colonna figlia** → ignorato; va sul `FormField`
  top-level.
- **Ordinamento non persistito** → manca `->positionKey(...)` o
  `->repeaterSortable()`.
- **Raggruppamento che non compare** → la colonna dichiarata in
  `repeaterGroupBy()` non esiste fra quelle del repeater, oppure la riga è una
  sola.

## Checklist

- [ ] `->repeater([...])` con `RepeaterColumn::key(...)` per ogni colonna
- [ ] per righe correlate: `->relation(RepeaterRelation::make(...))` sul campo
      top-level
- [ ] padre creato/risolto prima di `syncRepeaterRelations()`
- [ ] `positionKey` + `repeaterSortable()` se l'ordine conta
- [ ] con molte righe: `repeaterGroupBy()` sulla colonna che le distingue, e
      `repeaterGroupCommand()` sulla colonna che si ripete
