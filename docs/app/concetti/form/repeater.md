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
`repeaterGroupFixed($colonna)`,
`repeaterGroupFiles($campo, $colonnaChiave, $etichetta = 'Foto')`,
`repeaterAddButton($b = true)`,
`repeaterStartEmpty($b = true)`, `repeaterAdvanced(...$colonne)`,
`repeaterAdvancedLabel($s)`, `repeaterUndoDelete($b = true)`,
`repeaterUndoLabel($etichetta, $testo = '')`.

## Larghezza delle colonne

La riga è una griglia da dodici: `->columnSpan(n)` su una `RepeaterColumn` le
assegna `n` dodicesimi, **uno compreso**. Una colonna che non dichiara niente
prende undici dodicesimi — lo spazio che resta accanto ai bottoni. L'ultima
colonna, quella di elimina e riordino, è sempre in coda e vale uno (tre con
`repeaterSortable()`): le colonne dichiarate devono stare in undici.

Una colonna con `->columnSpan(12)` va a capo e si prende la riga intera: è il
modo di dare respiro a un campo che non si legge stretto, come un'area di
trascinamento per i file.

`->columnFill()` fa riempire alla colonna lo spazio che le altre lasciano
libero (classe `col` al posto di `col-N`) e vince su `columnSpan()`. Serve
quando accanto ci sono colonne che compaiono e spariscono: il campo che
riempie si allarga da sé invece di lasciare un buco. Fuori da un repeater non
fa niente.

### Colonne che compaiono con un altro campo

`visibleWhen()` e `hiddenWhen()` funzionano anche su una `RepeaterColumn`. Il
renderer ripete la regola sul contenitore della colonna e lo marca con
`data-wi-conditional-container`, così il JS nasconde la colonna intera — non
il genitore sbagliato, né il riquadro che contiene il repeater — e la regola
resta anche quando un widget sostituisce l'input (FilePond). Vale per le righe
già salvate, per la riga nuova e per il blocco delle informazioni avanzate.

```php
FormField::key('values')->repeater([
    RepeaterColumn::key('image')->fileDragDrop('image')->label('Fantasia')
        ->columnSpan(2)->visibleWhen('type', 'pattern'),
    RepeaterColumn::key('label')->text()->label('Valore')->columnFill(),
    RepeaterColumn::key('color')->color()->label('Colore')
        ->columnSpan(3)->visibleWhen('type', 'color'),
]);
```

```html
<div class="col-2" data-visible-when="type" data-visible-when-values="pattern" data-wi-conditional-container="true">…</div>
<div class="col">…</div>
```

Una colonna nascosta non lascia buchi: la colonna che riempie si prende il suo
spazio. I valori delle colonne nascoste vengono comunque inviati. Le colonne
scritte come array (`['col' => 4, ...]`) non hanno né regole né riempimento.

## Righe che si possono rimettere

Cancellare è una decisione che si rimpiange. Con `repeaterUndoDelete()` la
riga non se ne va: resta a schermo, sbiadita, con un bottone «Annulla».

```php
FormField::key('products')
    ->repeater([...])
    ->repeaterUndoDelete()
    ->repeaterUndoLabel('Annulla', 'Questa riga verrà eliminata al salvataggio.');
```

Il contratto è il `fieldset`: i campi della riga ci stanno dentro, e
disabilitarlo li toglie dal POST — `FormData` salta i controlli di un fieldset
spento, FilePond compreso. Per il server non cambia niente: una riga che non
arriva è una riga cancellata, come quando spariva dal DOM.

Due eventi salgono dal contenitore, per chi deve reagire:
`wi-repeater-row-delete` e `wi-repeater-row-restore`.

Con le righe annullabili il contenitore stampa anche una sentinella nascosta
(`nome[__wi_present]`): spegnendole tutte, il browser non posterebbe più la
chiave del repeater, e chi a valle controlla «c'è ma è vuoto» non avrebbe più
niente da guardare. Non è un array, quindi le righe non la vedono.

## Informazioni avanzate

Una riga chiede tutto quello che si può sapere, ma quasi nessuno lo sa nel
momento in cui la riga nasce: il codice a barre, lo stato, la foto arrivano
dopo. `repeaterAdvanced()` elenca le colonne che escono dalla riga e vanno in
un blocco a tutta larghezza, chiuso, che si apre da un bottone:

```php
FormField::key('products')
    ->repeater([
        RepeaterColumn::key('option')->text()->columnSpan(3),
        RepeaterColumn::key('price')->number()->columnSpan(2),
        RepeaterColumn::key('sku')->text()->columnSpan(4),
        RepeaterColumn::key('photo')->fileDragDrop('gallery')->columnSpan(12),
    ])
    ->repeaterAdvanced('sku', 'photo')
    ->repeaterAdvancedLabel('Compila le informazioni avanzate');
```

Le colonne avanzate restano nel DOM e vengono postate come tutte le altre:
sono nascoste, non tolte. Il blocco nasce sempre chiuso, anche su una riga che
ha già i suoi codici — molti di quei valori li propone il pannello, e una
griglia in cui ogni riga si apre da sola è la griglia lunga da cui si
scappava. Le larghezze dentro il blocco si contano su dodici, senza togliere
niente per i bottoni.

Fra le colonne avanzate può stare anche un bottone: `button()` non ha `name`,
quindi non posta niente, e il value della colonna diventa la didascalia
accanto. Con `opensModal()` apre una [finestra](../componenti/README.md#modal)
dichiarata una volta sola nel layout; la riga che l'ha aperta la trova lo
script della pagina in `event.relatedTarget` di `show.bs.modal`.

```php
FormField::key('products')
    ->repeater([
        RepeaterColumn::key('option')->text()->columnSpan(3),
        RepeaterColumn::key('sku')->text()->columnSpan(4),
        RepeaterColumn::key('cost_summary')->button('Costo')
            ->emptyCaption('Nessun fornitore')
            ->opensModal('wi-cost-modal')
            ->columnSpan(4),
    ])
    ->repeaterAdvanced('sku', 'cost_summary');
```

Ogni riga ha il suo bottone e la sua didascalia, anche la riga modello che il
bottone «Aggiungi» clona.

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

### File che stanno al gruppo

Certi file non sono di una riga ma del suo gruppo: le foto di un colore valgono
per tutte le sue taglie. `repeaterGroupFiles()` mette sulla testata un bottone
«Foto (n)» che apre, sotto la testata, un campo file:

```php
FormField::key('products')
    ->repeater([
        RepeaterColumn::key('group')->hidden(),        // l'etichetta del gruppo
        RepeaterColumn::key('group_value')->hidden(),  // la sua chiave
        // ...
    ])
    ->repeaterGroupFixed('group')
    ->repeaterGroupFiles(
        FormField::key('group_images')
            ->fileDragDrop('gallery')
            ->maxFile(10)
            ->value(['12' => ['blu-1.jpg'], '13' => []]),  // chiave → file
        'group_value',
        'Foto del colore'
    );
```

- **La chiave non è l'etichetta.** La colonna che raggruppa porta un nome da
  leggere; i file si legano a `$colonnaChiave`, letta nella prima riga del
  gruppo e ripulita di tutto ciò che non è lettera, cifra, trattino o
  underscore. Un gruppo con la chiave vuota non ha il bottone.
- **Il valore è una mappa** `chiave → nomi dei file`: il campo si stampa una
  volta sola nel `<template>` delle testate, e ogni testata prende i suoi.
- **Si posta come un campo a sé**: `group_images[12][]` con il manifesto
  `group_images[12__wi_files]`. Sul server
  `Support\Repeater::groupFilesFromRequest('group_images', $_POST, $_FILES)`
  restituisce, per chiave, `['manifest' => [...], 'files' => busta|null]`, con
  il manifesto già decodificato. Un gruppo senza manifesto non compare: il suo
  campo non era in pagina, e trattarlo come svuotato cancellerebbe i suoi file.
- **Solo con `repeaterGroupFixed()`**: con una colonna scelta da chi guarda, un
  gruppo potrebbe mescolare righe di chiavi diverse.

Per non perdere i caricamenti in corso, le testate **restano** da un
ricalcolo all'altro: si ritrovano per gruppo e per chiave, e ricordano anche
se erano chiuse. Una testata che sparisce spegne prima il suo FilePond. Quelle
che nascono dopo il caricamento della pagina (evento `loaded`) montano i loro
widget con `setInput(testata)`; prima ci pensa il giro su tutta la pagina.

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
  La chiave va al posto di `__ROW_KEY__` nei `name`, negli `id` e nei `for`
  delle etichette: le pillole hanno l'id fatto dal `name`, e con l'id del
  template in due righe il clic sulla seconda spunterebbe la prima.
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
- **Colonne larghe quanto la riga, una sotto l'altra** → la somma degli span
  supera undici, e Bootstrap manda a capo. Si contano le colonne dichiarate,
  non quelle nascoste.
- **`repeaterAdvanced()` che non nasconde niente** → il nome passato non è
  quello di una colonna del repeater: la chiave è la stessa di
  `RepeaterColumn::key(...)`.
- **Una radio che si spunta in una riga sola** → manca `->nested()`: i campi
  si chiamano `colonna[]`, e le radio di tutte le righe fanno un gruppo solo,
  con gli stessi id. Radio e pillole in un repeater vogliono le righe
  annidate, `campo[chiave][colonna]`.

## Checklist

- [ ] `->repeater([...])` con `RepeaterColumn::key(...)` per ogni colonna
- [ ] per righe correlate: `->relation(RepeaterRelation::make(...))` sul campo
      top-level
- [ ] padre creato/risolto prima di `syncRepeaterRelations()`
- [ ] `positionKey` + `repeaterSortable()` se l'ordine conta
- [ ] con molte righe: `repeaterGroupBy()` sulla colonna che le distingue, e
      `repeaterGroupCommand()` sulla colonna che si ripete
- [ ] file del gruppo: `repeaterGroupFixed()` + `repeaterGroupFiles()` con una
      colonna chiave nascosta, e `groupFilesFromRequest()` nel salvataggio
